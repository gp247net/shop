<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Core\AdminShell\Infrastructure\HasValidationLabels;
use GP247\Shop\Models\ShopSupplier;
use Illuminate\Validation\Rule;

/**
 * Supplier manager (shop-admin Unit) — two-panel screen (form left, list right)
 * on the shared core ResourcePanel base, matching the legacy
 * AdminSupplierController layout (rule ui-tailadmin P1). Image (LFM), name, alias
 * (auto, unique), url, email, phone, address, sort; store_id follows the active
 * admin store. Domain unchanged (ShopSupplier). Gated by `admin_supplier`.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-002
 * @aidlc-adr ADR-001, ADR-005, ADR-006, ADR-007
 */
class SupplierManager extends ResourcePanel
{
    use HasValidationLabels;

    protected ?string $permission = 'admin_supplier';

    /**
     * Keep list state (page/keyword/sort) and the edited record on screen when
     * editing/saving, instead of remounting via route navigation.
     *
     * @var bool
     * @aidlc-story US-AUI-two-panel-state-preservation
     * @aidlc-adr ADR-admin-shell-rbac-two-panel-state-preservation
     */
    protected bool $keepStateOnSave = true;

    /**
     * Store-scoped: pick a store on create (root admin), show it in the list, lock
     * it on edit. Supplier is a leaf entity (no cross-store related fields).
     *
     * @return array<string, mixed>|null
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-store-content-assignment
     * @aidlc-adr admin-shell_store-scoped-resource-panel
     */
    protected function storeScoped(): ?array
    {
        return ['display' => 'name', 'reset' => []];
    }

    /**
     * Store-scoped supplier query: root admin shows every store's suppliers; a
     * scoped context (store-admin/switcher) or single-store install filters to own.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        $query = ShopSupplier::query();
        if (!($this->storeScopeActive() && $this->isRootScope())) {
            $query->where('store_id', $this->storeContext());
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['name', 'email'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'email', 'sort'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    /**
     * @return string
     */
    protected function panelView(): string
    {
        return 'gp247-shop-admin::supplier-manager';
    }

    /**
     * @return string
     */
    protected function pageTitle(): string
    {
        return gp247_language_render('admin.supplier.title');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        return 'admin_supplier.index';
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDefaults(): array
    {
        return ['image' => '', 'name' => '', 'alias' => '', 'url' => '', 'email' => '', 'phone' => '', 'address' => '', 'sort' => 0];
    }

    /**
     * @param ShopSupplier $model
     * @return array<string, mixed>
     */
    protected function fillForm($model): array
    {
        // Store is immutable on edit — expose it for the read-only display.
        $this->formStoreId = (string) $model->store_id;

        return [
            'image' => (string) $model->image,
            'name' => (string) $model->name,
            'alias' => (string) $model->alias,
            'url' => (string) $model->url,
            'email' => (string) $model->email,
            'phone' => (string) $model->phone,
            'address' => (string) $model->address,
            'sort' => (int) $model->sort,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $table = (new ShopSupplier())->getTable();

        return [
            'form.name' => ['required', 'string', 'max:100'],
            'form.alias' => ['required', 'string', 'max:100', Rule::unique($table, 'alias')->ignore($this->editingId)],
            'form.image' => ['required', 'string'],
            'form.url' => ['nullable', 'url', 'max:255'],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.sort' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Reuse the existing v1 supplier label keys.
     *
     * @return array<string, string>
     */
    protected function attributeLabels(): array
    {
        return [
            'form.name' => 'admin.supplier.name',
            'form.alias' => 'admin.supplier.alias',
            'form.image' => 'admin.supplier.image',
            'form.url' => 'admin.supplier.url',
            'form.email' => 'admin.supplier.email',
            'form.sort' => 'admin.sort',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    protected function persist(array $data): void
    {
        $attributes = [
            'image' => $data['image'] ?? '',
            'name' => $data['name'],
            'alias' => $data['alias'],
            'url' => $data['url'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'address' => $data['address'] ?? '',
            'sort' => (int) ($data['sort'] ?? 0),
        ];

        if ($this->editingId !== null) {
            // Store is immutable on edit — do NOT touch store_id (ADR 1-1).
            ShopSupplier::findOrFail($this->editingId)->update($attributes);
        } else {
            // WHY: 1-1 ownership — a new supplier is owned by the store picked on
            // create (root admin) or the current scoped store (store-admin/switcher).
            $attributes['store_id'] = $this->resolveCreateStore();
            ShopSupplier::create($attributes);
        }
    }

    /**
     * @param int|string $id
     * @return void
     */
    protected function deleteModel($id): void
    {
        $model = $this->baseQuery()->find($id);
        if ($model !== null) {
            $model->delete();
        }
    }

    /**
     * Derive alias from name (brownfield parity) before the secure save pipeline.
     *
     * @return void
     */
    public function save(): void
    {
        if (empty($this->form['alias'])) {
            $this->form['alias'] = $this->form['name'] ?? '';
        }
        $this->form['alias'] = gp247_word_limit(gp247_word_format_url((string) $this->form['alias']), 100);

        parent::save();
    }
}
