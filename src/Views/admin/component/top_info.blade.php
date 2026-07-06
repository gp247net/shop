{{--
    Dashboard KPI stat cards block (ADR-005/007) — admin_home_layout view
    "gp247-shop-admin::component.top_info". Self-contained: queries its own
    totals here (guarded by gp247_shop_admin_model()) instead of receiving a
    data slice from Dashboard::blocks() (vendor/gp247/core), which now only
    renders whichever blocks are configured. Renders each stat via the shared
    `<x-gp247::stat-card>` (core, Views/admin/components).

    @aidlc-unit admin-shell
    @aidlc-story US-LW-001, US-AUI-005
    @aidlc-adr ADR-005, ADR-007
--}}
@php
    $adminOrder = gp247_shop_admin_model('AdminOrder');
    $adminProduct = gp247_shop_admin_model('AdminProduct');
    $adminCustomer = gp247_shop_admin_model('AdminCustomer');

    $stats = [];

    if ($adminOrder) {
        $stats[] = [
            'label' => gp247_language_render('admin.dashboard.total_order'),
            'value' => number_format(method_exists($adminOrder, 'getTotalOrder') ? $adminOrder::getTotalOrder() : $adminOrder::count()),
            'icon' => 'fas fa-shopping-cart',
            'color' => 'emerald',
            'url' => \Illuminate\Support\Facades\Route::has('admin_order.index') ? gp247_route_admin('admin_order.index') : null,
        ];
    }

    if ($adminProduct) {
        $stats[] = [
            'label' => gp247_language_render('admin.dashboard.total_product'),
            'value' => number_format(method_exists($adminProduct, 'getTotalProduct') ? $adminProduct::getTotalProduct() : $adminProduct::count()),
            'icon' => 'fas fa-tags',
            'color' => 'sky',
            'url' => \Illuminate\Support\Facades\Route::has('admin_product.index') ? gp247_route_admin('admin_product.index') : null,
        ];
    }

    if ($adminCustomer) {
        $stats[] = [
            'label' => gp247_language_render('admin.dashboard.total_customer'),
            'value' => number_format(method_exists($adminCustomer, 'getTotalCustomer') ? $adminCustomer::getTotalCustomer() : $adminCustomer::count()),
            'icon' => 'fas fa-users',
            'color' => 'amber',
            'url' => \Illuminate\Support\Facades\Route::has('admin_customer.index') ? gp247_route_admin('admin_customer.index') : null,
        ];
    }
@endphp

@if (!empty($stats))
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($stats as $stat)
            <x-gp247::stat-card
                :label="$stat['label']"
                :value="$stat['value']"
                :icon="$stat['icon']"
                :color="$stat['color']"
                :url="$stat['url']"
            />
        @endforeach
    </div>
@endif
