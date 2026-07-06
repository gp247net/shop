{{--
    Dashboard "latest orders" block (ADR-005/007) — admin_home_layout view
    "gp247-shop-admin::component.new_order". Self-contained: queries its own
    order list here (guarded by gp247_shop_admin_model()) instead of receiving
    a data slice from Dashboard::blocks() (vendor/gp247/core), which now only
    renders whichever blocks are configured.

    @aidlc-unit admin-shell
    @aidlc-story US-LW-001
    @aidlc-adr ADR-005, ADR-007
--}}
@php
    $adminOrder = gp247_shop_admin_model('AdminOrder');
    $topOrders = ($adminOrder && method_exists($adminOrder, 'getTopOrder'))
        ? $adminOrder::getTopOrder()
        : collect();
    $orderDetailRoute = \Illuminate\Support\Facades\Route::has('admin_order.detail') ? 'admin_order.detail' : null;
@endphp
<x-gp247::card :title="gp247_language_render('admin.dashboard.top_order_new')">
    <x-gp247::table :empty="$topOrders->isEmpty() ? gp247_language_render('admin.core.no_records') : null">
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">ID</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.email') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.status') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.created_at') }}</th>
            </tr>
        </x-slot:head>
        @foreach ($topOrders as $order)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50" wire:key="ord-{{ $order->id }}">
                <td class="px-4 py-3 text-sm font-medium">
                    @if ($orderDetailRoute)
                        <a href="{{ gp247_route_admin($orderDetailRoute, ['id' => $order->id]) }}" class="text-blue-600 hover:underline dark:text-blue-400">#{{ $order->id }}</a>
                    @else
                        <span class="text-gray-700 dark:text-gray-200">#{{ $order->id }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ $order->email }}</td>
                <td class="px-4 py-3 text-sm">
                    <x-gp247::badge color="blue">{{ $order->orderStatus->name ?? $order->status }}</x-gp247::badge>
                </td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $order->created_at }}</td>
            </tr>
        @endforeach
    </x-gp247::table>
</x-gp247::card>
