{{--
    U01 — Login. Tailwind port of vendor's auth/shop_login.blade.php.
    Extends the top-level layout directly (vendor overrides block_main; GP247Front
    overrides block_main_content_center) — same pattern as shop_verify.blade.php.

    Variables (unchanged from vendor):
    - none required beyond $errors/old(); social-login include is conditional

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@extends($GP247TemplatePath.'.layout')

@section('block_main_content_center')
<div class="lg:col-span-12 w-full card p-8 max-w-md mx-auto">
    <h1 class="section-title mb-6 text-center">{{ gp247_language_render('customer.title_login') }}</h1>

    <form method="POST" action="{{ gp247_route_front('customer.postLogin') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label">{{ gp247_language_render('customer.email') }}</label>
            <input type="email" class="input @error('email') input-error @enderror" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="label">{{ gp247_language_render('customer.password') }}</label>
            <input type="password" class="input @error('password') input-error @enderror" name="password" required>
            @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" class="rounded">
            {{ gp247_language_render('customer.remember_me') }}
        </label>

        <button type="submit" class="btn-primary w-full">{{ gp247_language_render('front.login') }}</button>
    </form>

    @if (gp247_extension_check_active('Plugins', 'LoginSocial'))
        <div class="divider my-6"></div>
        @include('Plugins/LoginSocial::render', ['type' => 'front'])
    @endif

    <div class="divider my-6"></div>

    <div class="flex items-center justify-between text-sm">
        <a href="{{ gp247_route_front('customer.forgot') }}" class="text-brand-600">{{ gp247_language_render('customer.password_forgot') }}</a>
        <a href="{{ gp247_route_front('customer.register') }}" class="text-brand-600">{{ gp247_language_render('customer.title_register') }}</a>
    </div>
</div>
@endsection
