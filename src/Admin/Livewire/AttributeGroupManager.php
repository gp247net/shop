<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
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
    protected ?string $permission = 'admin_attribute_group';

    /** Attribute-group input types, mirroring the legacy form (radio/select). */
    private const TYPES = ['radio', 'select'];

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        return ShopAttributeGroup::query();
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
            ShopAttributeGroup::findOrFail($this->editingId)->update($attributes);
        } else {
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
