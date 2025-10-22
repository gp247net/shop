<ul class="list-group list-group-flush member-nav">
    <li class="list-group-item">
        <a href="{{ gp247_route_front('customer.change_password') }}"><span class="fa fa-key" aria-hidden="true"></span> {{ gp247_language_render('customer.change_password') }}</a></li>
    <li class="list-group-item">
        <a href="{{ gp247_route_front('customer.change_infomation') }}"><span class="fa fa-list-alt" aria-hidden="true"></span> {{ gp247_language_render('customer.change_infomation') }}
        </a>
    </li>
    <li class="list-group-item">
        <a href="{{ gp247_route_front('customer.address_list') }}"><span class="fa fa-id-card-o" aria-hidden="true"></span> {{ gp247_language_render('customer.address_list') }}</a>
    </li>
    <li class="list-group-item">
        <a href="{{ gp247_route_front('customer.order_list') }}"><span class="fa fa-cart-arrow-down" aria-hidden="true"></span> {{ gp247_language_render('customer.order_history') }}</a>
    </li>

    @if(function_exists('mfa_get_guard_config') && mfa_get_guard_config('customer')['enabled'])
        <li class="list-group-item">
            <a href="{{ gp247_route_front('mfa.manage', ['guard' => 'customer']) }}"><span class="fa fa-shield" aria-hidden="true"></span> {{ gp247_language_render('Plugins/MFA::lang.admin_title') }}</a>
        </li>
    @endif

</ul>