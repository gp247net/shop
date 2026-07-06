{{--
    U03 — Forgot password. Tailwind port of vendor's auth/shop_forgot.blade.php.

    Variables (unchanged from vendor):
    - $viewCaptcha: rendered via gp247_captcha_processview('forgot', ...) in controller

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@extends($GP247TemplatePath.'.layout')

@section('block_main_content_center')
<div class="lg:col-span-12 w-full card p-8 max-w-md mx-auto">
    <h1 class="section-title mb-6 text-center">{{ gp247_language_render('customer.password_forgot') }}</h1>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 text-green-700 text-sm px-4 py-3 mb-4">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ gp247_route_front('customer.password_email') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label">{{ gp247_language_render('customer.email') }}</label>
            <input type="email" class="input @error('email') input-error @enderror" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        {!! $viewCaptcha ?? '' !!}

        <button type="submit" class="btn-primary w-full">{{ gp247_language_render('action.submit') }}</button>
    </form>
</div>
@endsection
