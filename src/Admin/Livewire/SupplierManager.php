<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Core\AdminShell\Infrastructure\HasValidationLabels;
use GP247\Shop\Models\ShopSupplier;
use GP247\Shop\Services\Catalog\SupplierWriter;
use GP247\Shop\Services\Catalog\WriteContext;

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
     * Language keys naming the searched columns (shown in the search box).
     *
     * @return array<string, string>
     *
     * @aidlc-story US-admin-shell-list-search-scope-hint
     */
    protected function searchLabels(): array
    {
        return [
            'name' => 'admin.supplier.name',
            'email' => 'admin.supplier.email',
        ];
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
     * The form rules, owned by the shared write service (US-SADM-catalog-write-service).
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return (new SupplierWriter)->rules($this->editingId);
    }

    /**
     * Reuse the existing v1 supplier label keys.
     *
     * @return array<string, string>
     */
    protected function attributeLabels(): array
    {
        return (new SupplierWriter)->labelKeys();
    }

    /**
     * Persist through the shared write service; the form was validated by save() with the
     * service's own rules.
     *
     * @param array<string, mixed> $data Sanitised form.
     * @return void
     *
     * @aidlc-story US-SADM-catalog-write-service
     * @aidlc-adr shop-admin_catalog-write-service
     */
    protected function persist(array $data): void
    {
        $editing = $this->editingId !== null && $this->editingId !== '';
        // Store is immutable on edit (ADR 1-1): the record's own store, read from the DB.
        $store = $editing ? (string) ShopSupplier::whereKey($this->editingId)->value('store_id') : (string) $this->resolveCreateStore();
        $record = (new SupplierWriter)->save(
            new WriteContext($store !== '' ? $store : (string) $this->storeContext()),
            ['form' => $data],
            $editing ? $this->editingId : null,
            ['validated' => true, 'clean' => false]
        );
        // ResourcePanel create contract: expose the id so save() edits it in place.
        $this->editingId = (string) $record->id;
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
        $this->form['alias'] = SupplierWriter::alias((string) ($this->form['alias'] ?? ''), (string) ($this->form['name'] ?? ''));

        parent::save();
    }
}
