{{--
    Dashboard "orders this month" chart block (ADR-004/005/007) —
    admin_home_layout view "gp247-shop-admin::component.order_month".
    Self-contained: builds its own $monthSeries here (guarded by
    gp247_shop_admin_model()) instead of receiving a data slice from
    Dashboard::blocks() (vendor/gp247/core), which now only renders whichever
    blocks are configured. Uses the shared ApexCharts partial (US-AUI-005) —
    no separate chart library. Sibling block: order_year.blade.php.

    @aidlc-unit admin-shell
    @aidlc-story US-LW-001, US-AUI-005
    @aidlc-adr ADR-004, ADR-005, ADR-007
--}}
@php
    $adminOrder = gp247_shop_admin_model('AdminOrder');

    // Daily order counts for the trailing month.
    $monthSeries = [];
    if ($adminOrder && method_exists($adminOrder, 'getSumOrderTotalInMonth')) {
        $monthTotals = collect($adminOrder::getSumOrderTotalInMonth())->keyBy('md');
        $monthPeriod = new \DatePeriod(
            new \DateTime('-1 month'),
            new \DateInterval('P1D'),
            new \DateTime('+1 day'),
        );
        foreach ($monthPeriod as $day) {
            $date = $day->format('m-d');
            $monthSeries[] = ['label' => $date, 'value' => (float) data_get($monthTotals->get($date), 'total_order', 0)];
        }
    }
@endphp
@if (!empty($monthSeries))
    <x-gp247::card :title="gp247_language_render('admin.dashboard.order_month')">
        <div class="text-gray-400 dark:text-gray-500">
            @include('gp247-admin::partials.dashboard-chart', ['series' => $monthSeries, 'color' => '#3b82f6', 'name' => gp247_language_render('admin.dashboard.order_month')])
        </div>
    </x-gp247::card>
@endif
