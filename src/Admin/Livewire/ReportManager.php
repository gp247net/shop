<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\GP247AdminComponent;
use GP247\Shop\Admin\Models\AdminCustomer;
use GP247\Shop\Admin\Models\AdminOrder;
use GP247\Shop\Admin\Models\AdminProduct;
use GP247\Shop\Models\ShopProduct;
use Illuminate\Contracts\View\View;

/**
 * Report screen (shop-admin Unit, group G, US-SADM-006): a read-only dashboard of
 * KPIs, order-trend charts (reusing the core pure-SVG partial — ADR-004) and a
 * top-products-by-sold table. Reuses the existing stat sources (AdminOrder /
 * AdminProduct / AdminCustomer); the domain is untouched (MC-008). Gated by
 * `admin_report`.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-006
 * @aidlc-adr ADR-001, ADR-004, ADR-006, ADR-007
 */
class ReportManager extends GP247AdminComponent
{
    protected ?string $permission = 'admin_report';

    /**
     * Current admin store id (falls back to the root store).
     *
     * @return int|string
     */
    private function storeId()
    {
        return session('adminStoreId', defined('GP247_STORE_ID_ROOT') ? GP247_STORE_ID_ROOT : 1);
    }

    /**
     * @return int Total number of orders.
     */
    public function totalOrders(): int
    {
        return (int) AdminOrder::getTotalOrder();
    }

    /**
     * @return int Total number of products.
     */
    public function totalProducts(): int
    {
        return (int) AdminProduct::getTotalProduct();
    }

    /**
     * @return int Total number of customers.
     */
    public function totalCustomers(): int
    {
        return (int) AdminCustomer::getTotalCustomer();
    }

    /**
     * @return int Number of new (status = 1) orders.
     */
    public function newOrders(): int
    {
        return (int) AdminOrder::getCountOrderNew();
    }

    /**
     * Completed-order revenue grouped by currency.
     *
     * @return array<int, array<string, mixed>>
     */
    public function revenueByCurrency(): array
    {
        return (array) AdminOrder::getSumAmountOrder($this->storeId());
    }

    /**
     * Top products by units sold (store-scoped), as display rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function topProducts(): array
    {
        $storeId = $this->storeId();

        return ShopProduct::whereHas('stores', static fn ($q) => $q->where('store_id', $storeId))
            ->orderBy('sold', 'desc')
            ->limit(10)
            ->get()
            ->map(static function ($product): array {
                return [
                    'id' => (string) $product->id,
                    'sku' => (string) $product->sku,
                    'name' => method_exists($product, 'getName') ? (string) ($product->getName() ?: $product->alias) : (string) $product->alias,
                    'sold' => (float) $product->sold,
                    'price' => (float) $product->price,
                ];
            })
            ->all();
    }

    /**
     * Daily order counts for the trailing month, as {label, value} points
     * (mirrors the core dashboard month series).
     *
     * @return array<int, array{label: string, value: float}>
     */
    public function monthSeries(): array
    {
        $totals = collect(AdminOrder::getSumOrderTotalInMonth())->keyBy('md');
        $series = [];
        $period = new \DatePeriod(new \DateTime('-1 month'), new \DateInterval('P1D'), new \DateTime('+1 day'));
        foreach ($period as $day) {
            $date = $day->format('m-d');
            $series[] = ['label' => $date, 'value' => (float) data_get($totals->get($date), 'total_order', 0)];
        }

        return $series;
    }

    /**
     * Monthly revenue for the trailing 13 months, as {label, value} points
     * (mirrors the core dashboard year series).
     *
     * @return array<int, array{label: string, value: float}>
     */
    public function yearSeries(): array
    {
        $totals = collect(AdminOrder::getSumOrderTotalInYear())->pluck('total_amount', 'ym');
        $series = [];
        for ($i = 12; $i >= 0; $i--) {
            $date = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
            $series[] = ['label' => $date, 'value' => (float) ($totals[$date] ?? 0)];
        }

        return $series;
    }

    /**
     * Render the report wrapped in the admin layout.
     *
     * @return View
     */
    public function render(): View
    {
        return view('gp247-shop-admin::report-manager', [
            'topProducts' => $this->topProducts(),
            'monthSeries' => $this->monthSeries(),
            'yearSeries' => $this->yearSeries(),
            'revenueByCurrency' => $this->revenueByCurrency(),
        ])->layout('gp247-admin::layouts.admin', ['title' => gp247_language_render('admin.report.title')]);
    }
}
