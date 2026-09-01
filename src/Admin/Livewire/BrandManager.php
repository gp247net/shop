<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Core\AdminShell\Infrastructure\HasValidationLabels;
use GP247\Shop\Models\ShopBrand;
use Illuminate\Validation\Rule;

/**
 * Brand manager (shop-admin Unit) — two-panel screen (add/edit form left, list
 * right) on the shared core ResourcePanel base, matching the legacy
 * AdminBrandController layout (rule ui-tailadmin P1). Image (LFM), name, alias
 * (auto from name, unique), url, sort, status. Domain unchanged (ShopBrand).
 * Gated by `admin_brand`.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-002
 * @aidlc-adr ADR-001, ADR-005, ADR-006, ADR-007
 */
class BrandManager extends ResourcePanel
{
    use HasValidationLabels;

    protected ?string $permission = 'admin_brand';

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
     * it on edit. Brand is a leaf entity (no cross-store related fields to reset).
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
     * Store-scoped brand query: root admin shows every store's brands (each row
     * labelled by its store); a scoped context (store-admin/switcher) or a
     * single-store install filters to the own store.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        $query = ShopBrand::query();
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
        return ['name'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'sort', 'status'];
    }

    /**
     * @return string
     */
    protected function panelView(): string
    {
        return 'gp247-shop-admin::brand-manager';
    }

    /**
     * @return string
     */
    protected function pageTitle(): string
    {
        return gp247_language_render('admin.brand.title');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        return 'admin_brand.index';
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDefaults(): array
    {
        return ['image' => '', 'name' => '', 'alias' => '', 'url' => '', 'sort' => 0, 'status' => 1];
    }

    /**
     * @param ShopBrand $model
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
            'sort' => (int) $model->sort,
            'status' => (int) $model->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $table = (new ShopBrand())->getTable();

        return [
            'form.name' => ['required', 'string', 'max:100'],
            'form.alias' => ['required', 'string', 'max:100', Rule::unique($table, 'alias')->ignore($this->editingId)],
            'form.image' => ['required', 'string'],
            'form.url' => ['nullable', 'url', 'max:255'],
            'form.sort' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Reuse the existing v1 brand label keys.
     *
     * @return array<string, string>
     */
    protected function attributeLabels(): array
    {
        return [
            'form.name' => 'admin.brand.name',
            'form.alias' => 'admin.brand.alias',
            'form.image' => 'admin.brand.image',
            'form.url' => 'admin.brand.url',
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
            'sort' => (int) ($data['sort'] ?? 0),
            'status' => empty($data['status']) ? 0 : 1,
        ];

        if ($this->editingId !== null) {
            // Store is immutable on edit — do NOT touch store_id (ADR 1-1).
            ShopBrand::findOrFail($this->editingId)->update($attributes);
        } else {
            // WHY: 1-1 ownership — a new brand is owned by the store picked on create
            // (root admin) or the current scoped store (store-admin / switcher).
            $attributes['store_id'] = $this->resolveCreateStore();
            ShopBrand::create($attributes);
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
