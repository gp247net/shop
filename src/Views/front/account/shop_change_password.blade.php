{{--
    Change password form. Tailwind port of vendor's account/shop_change_password.blade.php.
    The old-password field intentionally reads from Session::has/get('password_old_error')
    (a flash var set by ShopAccountController::postChangePassword()), NOT the standard
    $errors bag — preserved exactly as vendor implements it.

    Variables (unchanged from vendor):
    - $customer

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@php
    $view = gp247_shop_process_view($GP247TemplatePath, 'account.shop_layout');
@endphp
@extends($view)

@section('block_main_profile')
<form method="POST" action="{{ gp247_route_front('customer.post_change_password') }}" class="card p-6 max-w-xl space-y-4">
    @csrf
    <div>
        <label class="label">{{ gp247_language_render('customer.password_old') }}</label>
        <input type="password" class="input {{ Session::has('password_old_error') ? 'input-error' : '' }}" name="password_old">
        @if (Session::has('password_old_error'))
            <p class="text-xs text-red-600 mt-1">{{ Session::get('password_old_error') }}</p>
        @endif
    </div>

    <div>
        <label class="label">{{ gp247_language_render('customer.password_new') }}</label>
        <input type="password" class="input @error('password') input-error @enderror" name="password">
        @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="label">{{ gp247_language_render('customer.password_confirm') }}</label>
        <input type="password" class="input @error('password_confirmation') input-error @enderror" name="password_confirmation">
        @error('password_confirmation')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>

    <button class="btn-primary" type="submit">{{ gp247_language_render('customer.update_infomation') }}</button>
</form>
@endsection
