{{--
    Report (shop-admin Unit, group G, US-SADM-006) — read-only dashboard: KPI
    cards, order-trend charts (reusing the core pure-SVG partial, ADR-004), revenue
    by currency and a top-products-by-sold table. TailAdmin-first, no chart library
    (P2). Text via gp247_language_render.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-006
    @aidlc-adr ADR-004, ADR-006, ADR-007

    Variables: $topProducts, $monthSeries, $yearSeries, $revenueByCurrency.
--}}
<div class="space-y-6">
    {{-- KPI cards --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @php($kpis = [
            ['label' => gp247_language_render('admin.dashboard.total_order'), 'value' => $this->totalOrders(), 'icon' => 'fas fa-shopping-cart'],
            ['label' => gp247_language_render('order.status'), 'value' => $this->newOrders(), 'icon' => 'fas fa-bell'],
            ['label' => gp247_language_render('admin.dashboard.total_product'), 'value' => $this->totalProducts(), 'icon' => 'fas fa-box'],
            ['label' => gp247_language_render('admin.dashboard.total_customer'), 'value' => $this->totalCustomers(), 'icon' => 'fas fa-users'],
        ])
        @foreach ($kpis as $kpi)
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</p>
                    <p class="mt-1 text-xl font-bold text-gray-800 dark:text-gray-100">{{ number_format($kpi['value']) }}</p>
                </div>
                <span class="text-blue-500"><i class="{{ $kpi['icon'] }}"></i></span>
            </div>
        @endforeach
    </div>

    {{-- Charts --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-gp247::card :title="gp247_language_render('admin.dashboard.total_order')">
            @include('gp247-admin::partials.dashboard-chart', ['series' => $monthSeries, 'color' => '#3b82f6', 'labelEvery' => 3])
        </x-gp247::card>
        <x-gp247::card :title="gp247_language_render('order.totals.total')">
            @include('gp247-admin::partials.dashboard-chart', ['series' => $yearSeries, 'color' => '#10b981', 'labelEvery' => 1])
        </x-gp247::card>
    </div>

    {{-- Revenue by currency + Top products --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-gp247::card :title="gp247_language_render('order.totals.total')">
            <x-gp247::table :empty="empty($revenueByCurrency) ? gp247_language_render('admin.core.no_records') : null">
                <x-slot:head>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('currency.title') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.total') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($revenueByCurrency as $row)
                    <tr wire:key="rev-{{ $loop->index }}">
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ $row['currency'] ?? '' }}</td>
                        <td class="px-4 py-3 text-right text-sm font-medium text-gray-800 dark:text-gray-100">{{ number_format((float) ($row['total_sum'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </x-gp247::table>
        </x-gp247::card>

        <x-gp247::card :title="gp247_language_render('product.product')">
            <x-gp247::table :empty="empty($topProducts) ? gp247_language_render('admin.core.no_records') : null">
                <x-slot:head>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('product.sku') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('product.name') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('product.sold') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($topProducts as $product)
                    <tr wire:key="top-{{ $product['id'] }}">
                        <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-100">{{ $product['sku'] }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $product['name'] }}</td>
                        <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-300">{{ gp247_qty_format($product['sold']) }}</td>
                    </tr>
                @endforeach
            </x-gp247::table>
        </x-gp247::card>
    </div>
</div>
