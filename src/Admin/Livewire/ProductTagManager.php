<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Core\AdminShell\Infrastructure\HasValidationLabels;
use GP247\Shop\Models\ShopProductTag;
use Illuminate\Validation\Rule;

/**
 * Product keyword-tag manager (shop-admin Unit, US-SADM-product-tags) — two-panel
 * screen (add/edit form left, list right) on the shared core ResourcePanel base,
 * matching the BrandManager layout (rule ui-tailadmin P1). Manages the normalized
 * shop_product_tag taxonomy (name, alias auto-from-name & unique, status, sort) that
 * products link to via the pivot. Distinct from product_type (delivery type). Gated
 * by `admin_product` — the same permission that guards product editing.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-product-tags
 * @aidlc-adr shop-admin_product-tag-storage, shop-admin_product-tag-input-ux
 */
class ProductTagManager extends ResourcePanel
{
    use HasValidationLabels;

    protected ?string $permission = 'admin_product';

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
     * it on edit. Product tag is a leaf entity (no cross-store related fields).
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
     * Store-scoped tag query: root admin shows every store's tags; a scoped context
     * (store-admin/switcher) or a single-store install filters to the own store.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        $query = ShopProductTag::query();
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
        return ['name', 'alias'];
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
        return 'gp247-shop-admin::product-tag-manager';
    }

    /**
     * @return string
     */
    protected function pageTitle(): string
    {
        return gp247_language_render('admin.product_tag.title');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        return 'admin_product_tag.index';
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDefaults(): array
    {
        return ['name' => '', 'alias' => '', 'sort' => 0, 'status' => 1];
    }

    /**
     * @param ShopProductTag $model
     * @return array<string, mixed>
     */
    protected function fillForm($model): array
    {
        // Store is immutable on edit — expose it for the read-only display.
        $this->formStoreId = (string) $model->store_id;

        return [
            'name' => (string) $model->name,
            'alias' => (string) $model->alias,
            'sort' => (int) $model->sort,
            'status' => (int) $model->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $table = (new ShopProductTag())->getTable();

        return [
            'form.name' => ['required', 'string', 'max:100'],
            'form.alias' => ['required', 'string', 'max:120', Rule::unique($table, 'alias')->ignore($this->editingId)],
            'form.sort' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function attributeLabels(): array
    {
        return [
            'form.name' => 'admin.product_tag.name',
            'form.alias' => 'admin.product_tag.alias',
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
            'name' => $data['name'],
            'alias' => $data['alias'],
            'sort' => (int) ($data['sort'] ?? 0),
            'status' => empty($data['status']) ? 0 : 1,
        ];

        if ($this->editingId !== null) {
            // Store is immutable on edit — do NOT touch store_id (ADR 1-1).
            ShopProductTag::findOrFail($this->editingId)->update($attributes);
        } else {
            // WHY: 1-1 ownership — a new tag is owned by the store picked on create
            // (root admin) or the current scoped store (store-admin / switcher).
            $attributes['store_id'] = $this->resolveCreateStore();
            ShopProductTag::create($attributes);
        }
    }

    /**
     * @param int|string $id
     * @return void
     */
    protected function deleteModel($id): void
    {
        // ShopProductTag::deleting cascades the pivot detach (defined in the model boot);
        // deleting a tag only unlinks products, never deletes them.
        $model = $this->baseQuery()->find($id);
        if ($model !== null) {
            $model->delete();
        }
    }

    /**
     * Derive the alias from the name (parity with BrandManager) before the secure
     * save pipeline; normalize via the model's shared alias rule so a managed tag and
     * a self-added one resolve to the SAME slug.
     *
     * @return void
     */
    public function save(): void
    {
        if (empty($this->form['alias'])) {
            $this->form['alias'] = $this->form['name'] ?? '';
        }
        $this->form['alias'] = ShopProductTag::normalizeAlias((string) $this->form['alias']);

        parent::save();
    }
}
