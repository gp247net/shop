{{--
    U02 — Register. Tailwind port of vendor's auth/shop_register.blade.php.
    Keeps every gp247_config('customer_*') conditional field, the local
    common.render_form_custom_field include (with $customer = [] since this is
    a new registration), and the optional captcha view.

    Variables (unchanged from vendor):
    - $customer: [] (empty at registration time)
    - $countries
    - $viewCaptcha: rendered via gp247_captcha_processview('register', ...) in controller
    - $customFields

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@extends($GP247TemplatePath.'.layout')

@section('block_main_content_center')
<div class="lg:col-span-12 w-full card p-8 max-w-xl mx-auto">
    <h1 class="section-title mb-6 text-center">{{ gp247_language_render('customer.title_register') }}</h1>

    <form method="POST" action="{{ gp247_route_front('customer.postRegister') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label">{{ gp247_language_render('customer.email') }}</label>
            <input type="email" class="input @error('email') input-error @enderror" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        @if (gp247_config('customer_lastname'))
            <div>
                <label class="label">{{ gp247_language_render('customer.first_name') }}</label>
                <input type="text" class="input @error('first_name') input-error @enderror" name="first_name" value="{{ old('first_name') }}" required>
                @error('first_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">{{ gp247_language_render('customer.last_name') }}</label>
                <input type="text" class="input @error('last_name') input-error @enderror" name="last_name" value="{{ old('last_name') }}" required>
                @error('last_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        @else
            <div>
                <label class="label">{{ gp247_language_render('customer.name') }}</label>
                <input type="text" class="input @error('first_name') input-error @enderror" name="first_name" value="{{ old('first_name') }}" required>
                @error('first_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        @endif

        @if (gp247_config('customer_name_kana'))
            <div>
                <label class="label">{{ gp247_language_render('customer.first_name_kana') }}</label>
                <input type="text" class="input @error('first_name_kana') input-error @enderror" name="first_name_kana" value="{{ old('first_name_kana') }}">
                @error('first_name_kana')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">{{ gp247_language_render('customer.last_name_kana') }}</label>
                <input type="text" class="input @error('last_name_kana') input-error @enderror" name="last_name_kana" value="{{ old('last_name_kana') }}">
                @error('last_name_kana')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        @endif

        @if (gp247_config('customer_phone'))
            <div>
                <label class="label">{{ gp247_language_render('customer.phone') }}</label>
                <input type="text" class="input @error('phone') input-error @enderror" name="phone" value="{{ old('phone') }}" required>
                @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        @endif

        @if (gp247_config('customer_postcode'))
            <div>
                <label class="label">{{ gp247_language_render('customer.postcode') }}</label>
                <input type="text" class="input @error('postcode') input-error @enderror" name="postcode" value="{{ old('postcode') }}">
                @error('postcode')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        @endif

        <div>
            <label class="label">{{ gp247_language_render('customer.address1') }}</label>
            <input type="text" class="input @error('address1') input-error @enderror" name="address1" value="{{ old('address1') }}">
            @error('address1')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        @if (gp247_config('customer_address2'))
            <div>
                <label class="label">{{ gp247_language_render('customer.address2') }}</label>
                <input type="text" class="input @error('address2') input-error @enderror" name="address2" value="{{ old('address2') }}">
                @error('address2')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        @endif

        @if (gp247_config('customer_address3'))
            <div>
                <label class="label">{{ gp247_language_render('customer.address3') }}</label>
                <input type="text" class="input @error('address3') input-error @enderror" name="address3" value="{{ old('address3') }}">
                @error('address3')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        @endif

        @if (gp247_config('customer_company'))
            <div>
                <label class="label">{{ gp247_language_render('customer.company') }}</label>
                <input type="text" class="input @error('company') input-error @enderror" name="company" value="{{ old('company') }}">
                @error('company')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        @endif

        @if (gp247_config('customer_country'))
            <div>
                <label class="label">{{ gp247_language_render('customer.country') }}</label>
                <select class="input @error('country') input-error @enderror" name="country">
                    <option>__{{ gp247_language_render('customer.country') }}__</option>
                    @foreach ($countries as $k => $v)
                        <option value="{{ $k }}" {{ old('country') == $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
                @error('country')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        @endif

        @if (gp247_config('customer_sex'))
            <div>
                <label class="label">{{ gp247_language_render('customer.sex') }}</label>
                <select class="input @error('sex') input-error @enderror" name="sex">
                    <option value="1" {{ old('sex') == 1 ? 'selected' : '' }}>{{ gp247_language_render('customer.sex_men') }}</option>
                    <option value="0" {{ old('sex') == 0 ? 'selected' : '' }}>{{ gp247_language_render('customer.sex_women') }}</option>
                </select>
                @error('sex')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        @endif

        @if (gp247_config('customer_birthday'))
            <div>
                <label class="label">{{ gp247_language_render('customer.birthday') }}</label>
                <input type="text" class="input flatpickr-date @error('birthday') input-error @enderror" name="birthday" value="{{ old('birthday') }}">
                @error('birthday')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        @endif

        @if (gp247_config('customer_group'))
            <div>
                <label class="label">{{ gp247_language_render('customer.group') }}</label>
                <select class="input @error('group') input-error @enderror" name="group">
                    @foreach (\GP247\Shop\Models\ShopCustomerGroup::pluck('name', 'id') as $k => $v)
                        <option value="{{ $k }}" {{ old('group') == $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
                @error('group')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        @endif

        <div>
            <label class="label">{{ gp247_language_render('customer.password') }}</label>
            <input type="password" class="input @error('password') input-error @enderror" name="password" required>
            @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="label">{{ gp247_language_render('customer.password_confirm') }}</label>
            <input type="password" class="input @error('password_confirmation') input-error @enderror" name="password_confirmation" required>
            @error('password_confirmation')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        @include($GP247TemplatePath.'.common.render_form_custom_field', ['object' => $customer])

        {!! $viewCaptcha ?? '' !!}

        <button type="submit" class="btn-primary w-full">{{ gp247_language_render('customer.signup') }}</button>
    </form>

    <div class="divider my-6"></div>

    <div class="text-center text-sm">
        <a href="{{ gp247_route_front('customer.login') }}" class="text-brand-600">{{ gp247_language_render('front.login') }}</a>
    </div>
</div>
@endsection
