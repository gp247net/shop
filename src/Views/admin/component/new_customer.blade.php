{{--
    Dashboard "latest customers" block (ADR-005/007) — admin_home_layout view
    "gp247-shop-admin::component.new_customer". Self-contained: queries its
    own customer list here (guarded by gp247_shop_admin_model()) instead of
    receiving a data slice from Dashboard::blocks() (vendor/gp247/core), which
    now only renders whichever blocks are configured.

    @aidlc-unit admin-shell
    @aidlc-story US-LW-001
    @aidlc-adr ADR-005, ADR-007
--}}
@php
    $adminCustomer = gp247_shop_admin_model('AdminCustomer');
    $topCustomers = ($adminCustomer && method_exists($adminCustomer, 'getTopCustomer'))
        ? $adminCustomer::getTopCustomer()
        : collect();
    $customerEditRoute = \Illuminate\Support\Facades\Route::has('admin_customer.edit') ? 'admin_customer.edit' : null;
@endphp
<x-gp247::card :title="gp247_language_render('admin.dashboard.top_customer_new')">
    <x-gp247::table :empty="$topCustomers->isEmpty() ? gp247_language_render('admin.no_records') : null">
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.email') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.name') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.created_at') }}</th>
            </tr>
        </x-slot:head>
        @foreach ($topCustomers as $customer)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50" wire:key="cus-{{ $customer->id }}">
                <td class="px-4 py-3 text-sm font-medium">
                    @if ($customerEditRoute)
                        <a href="{{ gp247_route_admin($customerEditRoute, ['id' => $customer->id]) }}" class="text-blue-600 hover:underline dark:text-blue-400">{{ $customer->email }}</a>
                    @else
                        <span class="text-gray-700 dark:text-gray-200">{{ $customer->email }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ $customer->name }}</td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $customer->created_at }}</td>
            </tr>
        @endforeach
    </x-gp247::table>
</x-gp247::card>
