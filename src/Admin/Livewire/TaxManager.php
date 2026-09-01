<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Core\AdminShell\Infrastructure\HasValidationLabels;
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
    use HasValidationLabels;

    protected ?string $permission = 'admin_tax';

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
     * it on edit. Tax is a leaf entity (no cross-store related fields to reset).
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
     * Store-scoped tax query: root admin shows every store's taxes; a scoped context
     * (store-admin/switcher) or a single-store install filters to the own store.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        $query = ShopTax::query();
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
        // Store is immutable on edit — expose it for the read-only display.
        $this->formStoreId = (string) $model->store_id;

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
     * Reuse the existing v1 tax label keys.
     *
     * @return array<string, string>
     */
    protected function attributeLabels(): array
    {
        return [
            'form.name' => 'admin.tax.name',
            'form.value' => 'admin.tax.value',
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
            // Store is immutable on edit — do NOT touch store_id (ADR 1-1).
            ShopTax::findOrFail($this->editingId)->update($attributes);
        } else {
            // WHY: 1-1 ownership — a new tax is owned by the store picked on create
            // (root admin) or the current scoped store (store-admin / switcher).
            $attributes['store_id'] = $this->resolveCreateStore();
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
