<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Shop\Models\ShopCurrency;
use Illuminate\Validation\Rule;

/**
 * Currency manager (shop-admin Unit) — two-panel screen (form left, list right)
 * on the shared core ResourcePanel base, matching the legacy
 * AdminCurrencyController layout (rule ui-tailadmin P1). Name, code (unique),
 * symbol, exchange rate, precision, symbol-first, thousands, status, sort. Domain
 * unchanged (ShopCurrency). Gated by `admin_currency`.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-005
 * @aidlc-adr ADR-001, ADR-005, ADR-006, ADR-007
 */
class CurrencyManager extends ResourcePanel
{
    protected ?string $permission = 'admin_currency';

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        return ShopCurrency::query();
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['name', 'code'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'code', 'exchange_rate', 'sort', 'status'];
    }

    /**
     * @return string
     */
    protected function panelView(): string
    {
        return 'gp247-shop-admin::currency-manager';
    }

    /**
     * @return string
     */
    protected function pageTitle(): string
    {
        return gp247_language_render('admin.currency.title');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        return 'admin_currency.index';
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDefaults(): array
    {
        return [
            'name' => '', 'code' => '', 'symbol' => '', 'exchange_rate' => 1,
            'precision' => 2, 'symbol_first' => 0, 'thousands' => ',', 'status' => 1, 'sort' => 0,
        ];
    }

    /**
     * @param ShopCurrency $model
     * @return array<string, mixed>
     */
    protected function fillForm($model): array
    {
        return [
            'name' => (string) $model->name,
            'code' => (string) $model->code,
            'symbol' => (string) $model->symbol,
            'exchange_rate' => (float) $model->exchange_rate,
            'precision' => (int) $model->precision,
            'symbol_first' => (int) $model->symbol_first,
            'thousands' => (string) $model->thousands,
            'status' => (int) $model->status,
            'sort' => (int) $model->sort,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $table = (new ShopCurrency())->getTable();

        return [
            'form.name' => ['required', 'string', 'max:100'],
            'form.code' => ['required', 'string', 'max:10', Rule::unique($table, 'code')->ignore($this->editingId)],
            'form.symbol' => ['required', 'string', 'max:20'],
            'form.exchange_rate' => ['required', 'numeric', 'gt:0'],
            'form.precision' => ['required', 'integer', 'min:0', 'max:8'],
            'form.symbol_first' => ['required', 'in:0,1'],
            'form.thousands' => ['required', 'string', 'max:2'],
            'form.sort' => ['required', 'numeric', 'min:0'],
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
            'code' => $data['code'],
            'symbol' => $data['symbol'],
            'exchange_rate' => $data['exchange_rate'],
            'precision' => (int) $data['precision'],
            'symbol_first' => (int) $data['symbol_first'],
            'thousands' => $data['thousands'],
            'status' => empty($data['status']) ? 0 : 1,
            'sort' => (int) ($data['sort'] ?? 0),
        ];

        if ($this->editingId !== null) {
            ShopCurrency::findOrFail($this->editingId)->update($attributes);
        } else {
            ShopCurrency::create($attributes);
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
