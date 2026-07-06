{{--
    Account sidebar navigation. Tailwind port of vendor's account/shop_nav_customer.blade.php
    (4 links: change_password, change_infomation, address_list, order_list — no
    "Dashboard" link exists in the vendor nav either; the dashboard/customer.index
    route is only reached via the header account icon, already wired in
    layout/block_menu.blade.php). Active-state highlighting via request()->routeIs()
    is new (Tailwind `.sidebar-link.active`) — a presentational addition only,
    no new route/behavior.

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
<nav class="card p-2 space-y-1">
    <a href="{{ gp247_route_front('customer.index') }}" class="sidebar-link {{ request()->routeIs('customer.index') ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l2-2 7-7 7 7 2 2M5 10v10h14V10"/></svg>
        {{ gp247_language_render('customer.my_account') }}
    </a>
    <a href="{{ gp247_route_front('customer.order_list') }}" class="sidebar-link {{ request()->routeIs('customer.order_list') || request()->routeIs('customer.order_detail') ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h2l2.6 12.4a2 2 0 002 1.6h8.8a2 2 0 002-1.6L21 8H6"/></svg>
        {{ gp247_language_render('customer.order_history') }}
    </a>
    <a href="{{ gp247_route_front('customer.address_list') }}" class="sidebar-link {{ request()->routeIs('customer.address_list') || request()->routeIs('customer.update_address') ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-6-5.2-6-10a6 6 0 1112 0c0 4.8-6 10-6 10z"/><circle cx="12" cy="11" r="2"/></svg>
        {{ gp247_language_render('customer.address_list') }}
    </a>
    <a href="{{ gp247_route_front('customer.change_infomation') }}" class="sidebar-link {{ request()->routeIs('customer.change_infomation') ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
        {{ gp247_language_render('customer.change_infomation') }}
    </a>
    <a href="{{ gp247_route_front('customer.change_password') }}" class="sidebar-link {{ request()->routeIs('customer.change_password') ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 118 0v4"/></svg>
        {{ gp247_language_render('customer.change_password') }}
    </a>
    @if (function_exists('mfa_get_guard_config') && mfa_get_guard_config('customer')['enabled'])
        <a href="{{ gp247_route_front('mfa.manage', ['guard' => 'customer']) }}" class="sidebar-link">
            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l8 4v6c0 5-3.4 8.7-8 10-4.6-1.3-8-5-8-10V6l8-4z"/></svg>
            {{ gp247_language_quickly('mfa.manage', 'MFA') }}
        </a>
    @endif
</nav>
