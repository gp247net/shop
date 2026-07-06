{{--
    Dashboard "orders this year" chart block (ADR-004/005/007) —
    admin_home_layout view "gp247-shop-admin::component.order_year".
    Self-contained: builds its own $yearSeries here (guarded by
    gp247_shop_admin_model()) instead of receiving a data slice from
    Dashboard::blocks() (vendor/gp247/core), which now only renders whichever
    blocks are configured. Uses the shared ApexCharts partial (US-AUI-005) —
    no separate chart library. Sibling block: order_month.blade.php.

    @aidlc-unit admin-shell
    @aidlc-story US-LW-001, US-AUI-005
    @aidlc-adr ADR-004, ADR-005, ADR-007
--}}
@php
    $adminOrder = gp247_shop_admin_model('AdminOrder');

    // Monthly order revenue for the trailing 13 months.
    $yearSeries = [];
    if ($adminOrder && method_exists($adminOrder, 'getSumOrderTotalInYear')) {
        $yearTotals = collect($adminOrder::getSumOrderTotalInYear())->pluck('total_amount', 'ym');
        for ($i = 12; $i >= 0; $i--) {
            $date = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
            $yearSeries[] = ['label' => $date, 'value' => (float) ($yearTotals[$date] ?? 0)];
        }
    }
@endphp
@if (!empty($yearSeries))
    <x-gp247::card :title="gp247_language_render('admin.dashboard.order_year')">
        <div class="text-gray-400 dark:text-gray-500">
            @include('gp247-admin::partials.dashboard-chart', ['series' => $yearSeries, 'color' => '#10b981', 'name' => gp247_language_render('admin.dashboard.order_year')])
        </div>
    </x-gp247::card>
@endif
