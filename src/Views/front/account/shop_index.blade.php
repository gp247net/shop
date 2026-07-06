{{--
    A01 — Account dashboard. Vendor's own view (shop_index.blade.php) is a
    one-line welcome message — the controller (ShopAccountController::index())
    only passes $customer, no order-count/total-spent aggregate — so unlike
    ecommerce-template/account.html's 3 mock stat cards (orders/spend/reward
    points, none backed by real data), this stays minimal: a welcome card plus
    quick links to the other account sections.

    Variables (unchanged from vendor):
    - $customer: authenticated ShopCustomer (Eloquent model, ArrayAccess)

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@php
    $view = gp247_shop_process_view($GP247TemplatePath, 'account.shop_layout');
@endphp
@extends($view)

@section('block_main_profile')
<div class="card p-6">
    <p class="text-lg font-semibold text-ink-800">
        {{ gp247_language_quickly('customer.welcome', 'Welcome') }}, {{ $customer['first_name'] }} {{ $customer['last_name'] }}!
    </p>
    <p class="text-sm text-ink-400 mt-1">{{ $customer['email'] }}</p>
</div>

<div class="grid sm:grid-cols-3 gap-4 mt-6">
    <a href="{{ gp247_route_front('customer.order_list') }}" class="card p-5 card-hover text-center">
        <p class="text-sm font-semibold text-ink-800">{{ gp247_language_render('customer.order_history') }}</p>
    </a>
    <a href="{{ gp247_route_front('customer.address_list') }}" class="card p-5 card-hover text-center">
        <p class="text-sm font-semibold text-ink-800">{{ gp247_language_render('customer.address_list') }}</p>
    </a>
    <a href="{{ gp247_route_front('customer.change_infomation') }}" class="card p-5 card-hover text-center">
        <p class="text-sm font-semibold text-ink-800">{{ gp247_language_render('customer.change_infomation') }}</p>
    </a>
</div>
@endsection
