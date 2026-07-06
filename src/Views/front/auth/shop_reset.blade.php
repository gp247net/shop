{{--
    U04 — Reset password. Tailwind port of vendor's auth/shop_reset.blade.php.
    No $email variable is passed by ResetPasswordController::_showResetForm() —
    only title/token/layout_page/breadcrumbs — so the email field starts empty,
    matching vendor exactly (confirmed by reading the controller directly).

    Variables (unchanged from vendor):
    - $token

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@extends($GP247TemplatePath.'.layout')

@section('block_main_content_center')
<div class="lg:col-span-12 w-full card p-8 max-w-md mx-auto">
    <h1 class="section-title mb-6 text-center">{{ gp247_language_render('customer.password_reset') }}</h1>

    <form method="POST" action="{{ gp247_route_front('customer.password_request') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label class="label">{{ gp247_language_render('customer.email') }}</label>
            <input type="email" class="input @error('email') input-error @enderror" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="label">{{ gp247_language_render('customer.password_new') }}</label>
            <input type="password" class="input @error('password') input-error @enderror" name="password" required>
            @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="label">{{ gp247_language_render('customer.password_confirm') }}</label>
            <input type="password" class="input @error('password_confirmation') input-error @enderror" name="password_confirmation" required>
            @error('password_confirmation')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="btn-primary w-full">{{ gp247_language_render('customer.password_reset') }}</button>
    </form>
</div>
@endsection
