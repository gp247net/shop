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
    // Config-driven columns (mirror the Customer list screen): email is optional,
    // name + address always render. Provider badge only when the LoginSocial
    // plugin is installed — the socialAccount() relation returns null without it,
    // so class_exists must gate every access (getTopCustomer eager-loads it).
    $showEmail = (bool) gp247_config_admin('customer_email');
    $hasSocialPlugin = class_exists(\App\GP247\Plugins\LoginSocial\Models\SocialAccount::class);
@endphp
<x-gp247::card :title="gp247_language_render('admin.dashboard.top_customer_new')">
    <x-gp247::table :empty="$topCustomers->isEmpty() ? gp247_language_render('admin.no_records') : null">
        <x-slot:head>
            <tr>
                @if ($showEmail)
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.email') }}</th>
                @endif
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.name') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.address1') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.created_at') }}</th>
            </tr>
        </x-slot:head>
        @foreach ($topCustomers as $customer)
            @php
                // Merge address parts the same way OrderManager does (address1..3).
                $customerAddress = trim($customer->address1 . ' ' . $customer->address2 . ' ' . $customer->address3);
                $socialProvider = $hasSocialPlugin ? optional($customer->socialAccount)->provider : null;
            @endphp
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50" wire:key="cus-{{ $customer->id }}">
                @if ($showEmail)
                    <td class="px-4 py-3 text-sm font-medium">
                        @if ($customerEditRoute)
                            <a href="{{ gp247_route_admin($customerEditRoute, ['id' => $customer->id]) }}" class="text-blue-600 hover:underline dark:text-blue-400">{{ $customer->email }}</a>
                        @else
                            <span class="text-gray-700 dark:text-gray-200">{{ $customer->email }}</span>
                        @endif
                    </td>
                @endif
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                    <div class="flex items-center gap-2">
                        @if ($customerEditRoute && !$showEmail)
                            <a href="{{ gp247_route_admin($customerEditRoute, ['id' => $customer->id]) }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">{{ $customer->name }}</a>
                        @else
                            <span>{{ $customer->name }}</span>
                        @endif
                        @if ($socialProvider)
                            <x-gp247::badge color="gray">{{ ucfirst($socialProvider) }}</x-gp247::badge>
                        @endif
                    </div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $customerAddress }}</td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $customer->created_at }}</td>
            </tr>
        @endforeach
    </x-gp247::table>
</x-gp247::card>
