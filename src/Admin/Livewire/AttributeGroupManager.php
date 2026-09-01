<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Core\AdminShell\Infrastructure\HasValidationLabels;
use GP247\Shop\Models\ShopAttributeGroup;

/**
 * Attribute-group manager (shop-admin Unit) — two-panel screen (add/edit form
 * left, list right) on the shared core ResourcePanel base, matching the legacy
 * AdminAttributeGroupController layout (rule ui-tailadmin P1): name + type
 * (radio/select). Deleting a group cascades its attribute items (model boot).
 * Domain unchanged (ShopAttributeGroup). Gated by `admin_attribute_group`.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-002
 * @aidlc-adr ADR-001, ADR-005, ADR-006, ADR-007
 */
class AttributeGroupManager extends ResourcePanel
{
    use HasValidationLabels;

    protected ?string $permission = 'admin_attribute_group';

    /**
     * Keep list state (page/keyword/sort) and the edited record on screen when
     * editing/saving, instead of remounting via route navigation.
     *
     * @var bool
     * @aidlc-story US-AUI-two-panel-state-preservation
     * @aidlc-adr ADR-admin-shell-rbac-two-panel-state-preservation
     */
    protected bool $keepStateOnSave = true;

    /** Attribute-group input types, mirroring the legacy form (radio/select). */
    private const TYPES = ['radio', 'select'];

    /**
     * Store-scoped: pick a store on create (root admin), show it in the list, lock
     * it on edit. Attribute group is a leaf entity (type is an enum, not a ref).
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
     * Store-scoped attribute-group query: root admin shows every store's groups; a
     * scoped context (store-admin/switcher) or single-store install filters to own.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        $query = ShopAttributeGroup::query();
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
        return ['name', 'type', 'id'];
    }

    /**
     * @return string
     */
    protected function panelView(): string
    {
        return 'gp247-shop-admin::attribute-group-manager';
    }

    /**
     * @return string
     */
    protected function pageTitle(): string
    {
        return gp247_language_render('admin.product_attribute_group.list');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        return 'admin_attribute_group.index';
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDefaults(): array
    {
        return ['name' => '', 'type' => 'radio'];
    }

    /**
     * @param ShopAttributeGroup $model
     * @return array<string, mixed>
     */
    protected function fillForm($model): array
    {
        // Store is immutable on edit — expose it for the read-only display.
        $this->formStoreId = (string) $model->store_id;

        return [
            'name' => (string) $model->name,
            'type' => (string) $model->type,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.type' => ['required', 'string', 'in:' . implode(',', self::TYPES)],
        ];
    }

    /**
     * Reuse the existing v1 attribute-group label keys.
     *
     * @return array<string, string>
     */
    protected function attributeLabels(): array
    {
        return [
            'form.name' => 'admin.product_attribute_group.name',
            'form.type' => 'admin.product_attribute_group.type',
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
            'type' => $data['type'],
        ];

        if ($this->editingId !== null) {
            // Store is immutable on edit — do NOT touch store_id (ADR 1-1).
            ShopAttributeGroup::findOrFail($this->editingId)->update($attributes);
        } else {
            // WHY: 1-1 ownership — a new group is owned by the store picked on create
            // (root admin) or the current scoped store (store-admin / switcher).
            $attributes['store_id'] = $this->resolveCreateStore();
            ShopAttributeGroup::create($attributes);
        }
    }

    /**
     * @param int|string $id
     * @return void
     */
    protected function deleteModel($id): void
    {
        // ShopAttributeGroup::boot() cascades its attribute items on delete.
        $model = $this->baseQuery()->find($id);
        if ($model !== null) {
            $model->delete();
        }
    }

    /**
     * Type options (value => label) for the form select.
     *
     * @return array<string, string>
     */
    public function typeOptions(): array
    {
        return ['radio' => 'Radio', 'select' => 'Select'];
    }
}
