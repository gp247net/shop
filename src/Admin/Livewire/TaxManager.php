<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Shop\Models\ShopTax;

/**
 * Tax manager (shop-admin Unit) — two-panel screen (form left, list right) on the
 * shared core ResourcePanel base, matching the legacy AdminTaxController layout
 * (rule ui-tailadmin P1). Name and numeric value. Domain unchanged (ShopTax).
 * Gated by `admin_tax`.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-005
 * @aidlc-adr ADR-001, ADR-005, ADR-006, ADR-007
 */
class TaxManager extends ResourcePanel
{
    protected ?string $permission = 'admin_tax';

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        return ShopTax::query();
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
        return ['name', 'value'];
    }

    /**
     * @return string
     */
    protected function panelView(): string
    {
        return 'gp247-shop-admin::tax-manager';
    }

    /**
     * @return string
     */
    protected function pageTitle(): string
    {
        return gp247_language_render('admin.tax.title');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        return 'admin_tax.index';
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDefaults(): array
    {
        return ['name' => '', 'value' => 0];
    }

    /**
     * @param ShopTax $model
     * @return array<string, mixed>
     */
    protected function fillForm($model): array
    {
        return ['name' => (string) $model->name, 'value' => (float) $model->value];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.value' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    protected function persist(array $data): void
    {
        $attributes = ['name' => $data['name'], 'value' => $data['value']];

        if ($this->editingId !== null) {
            ShopTax::findOrFail($this->editingId)->update($attributes);
        } else {
            ShopTax::create($attributes);
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
}
