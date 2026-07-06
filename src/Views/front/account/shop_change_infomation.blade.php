{{--
    Change customer info form. Tailwind port of vendor's account/shop_change_infomation.blade.php.
    Email stays a read-only display (not an input) — matches vendor exactly.
    Includes the local common.render_form_custom_field (see comment in that file
    for why it must be local, not resolved via gp247_shop_process_view()).

    Variables (unchanged from vendor):
    - $customer, $countries

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@php
    $view = gp247_shop_process_view($GP247TemplatePath, 'account.shop_layout');
@endphp
@extends($view)

@section('block_main_profile')
<form method="POST" action="{{ gp247_route_front('customer.post_change_infomation') }}" class="card p-6 max-w-xl space-y-4">
    @csrf
    <div>
        <label class="label">{{ gp247_language_render('customer.email') }}</label>
        <input type="text" class="input" value="{{ $customer['email'] }}" disabled>
    </div>

    @if (gp247_config('customer_lastname'))
        <div>
            <label class="label">{{ gp247_language_render('customer.first_name') }}</label>
            <input type="text" class="input @error('first_name') input-error @enderror" name="first_name" required value="{{ old('first_name', $customer['first_name']) }}">
            @error('first_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label">{{ gp247_language_render('customer.last_name') }}</label>
            <input type="text" class="input @error('last_name') input-error @enderror" name="last_name" required value="{{ old('last_name', $customer['last_name']) }}">
            @error('last_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @else
        <div>
            <label class="label">{{ gp247_language_render('customer.name') }}</label>
            <input type="text" class="input @error('first_name') input-error @enderror" name="first_name" required value="{{ old('first_name', $customer['first_name']) }}">
            @error('first_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    @if (gp247_config('customer_name_kana'))
        <div>
            <label class="label">{{ gp247_language_render('customer.first_name_kana') }}</label>
            <input type="text" class="input @error('first_name_kana') input-error @enderror" name="first_name_kana" value="{{ old('first_name_kana', $customer['first_name_kana']) }}">
            @error('first_name_kana')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label">{{ gp247_language_render('customer.last_name_kana') }}</label>
            <input type="text" class="input @error('last_name_kana') input-error @enderror" name="last_name_kana" value="{{ old('last_name_kana', $customer['last_name_kana']) }}">
            @error('last_name_kana')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    @if (gp247_config('customer_phone'))
        <div>
            <label class="label">{{ gp247_language_render('customer.phone') }}</label>
            <input type="text" class="input @error('phone') input-error @enderror" name="phone" required value="{{ old('phone', $customer['phone']) }}">
            @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    @if (gp247_config('customer_postcode'))
        <div>
            <label class="label">{{ gp247_language_render('customer.postcode') }}</label>
            <input type="text" class="input @error('postcode') input-error @enderror" name="postcode" value="{{ old('postcode', $customer['postcode']) }}">
            @error('postcode')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    <div>
        <label class="label">{{ gp247_language_render('customer.address1') }}</label>
        <input type="text" class="input @error('address1') input-error @enderror" name="address1" value="{{ old('address1', $customer['address1']) }}">
        @error('address1')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>

    @if (gp247_config('customer_address2'))
        <div>
            <label class="label">{{ gp247_language_render('customer.address2') }}</label>
            <input type="text" class="input @error('address2') input-error @enderror" name="address2" value="{{ old('address2', $customer['address2']) }}">
            @error('address2')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    @if (gp247_config('customer_address3'))
        <div>
            <label class="label">{{ gp247_language_render('customer.address3') }}</label>
            <input type="text" class="input @error('address3') input-error @enderror" name="address3" value="{{ old('address3', $customer['address3']) }}">
            @error('address3')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    @if (gp247_config('customer_company'))
        <div>
            <label class="label">{{ gp247_language_render('customer.company') }}</label>
            <input type="text" class="input @error('company') input-error @enderror" name="company" value="{{ old('company', $customer['company']) }}">
            @error('company')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    @if (gp247_config('customer_country'))
        @php $country = old('country', $customer['country']); @endphp
        <div>
            <label class="label">{{ gp247_language_render('customer.country') }}</label>
            <select class="input @error('country') input-error @enderror" name="country">
                <option>__{{ gp247_language_render('customer.country') }}__</option>
                @foreach ($countries as $k => $v)
                    <option value="{{ $k }}" {{ $country == $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
            @error('country')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    @if (gp247_config('customer_sex'))
        <div>
            <label class="label">{{ gp247_language_render('customer.sex') }}</label>
            <select class="input @error('sex') input-error @enderror" name="sex">
                <option value="1" {{ old('sex', $customer['sex']) == 1 ? 'selected' : '' }}>{{ gp247_language_render('customer.sex_men') }}</option>
                <option value="0" {{ old('sex', $customer['sex']) == 0 ? 'selected' : '' }}>{{ gp247_language_render('customer.sex_women') }}</option>
            </select>
            @error('sex')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    @if (gp247_config('customer_birthday'))
        <div>
            <label class="label">{{ gp247_language_render('customer.birthday') }}</label>
            <input type="text" class="input flatpickr-date @error('birthday') input-error @enderror" name="birthday" value="{{ old('birthday', $customer['birthday']) }}">
            @error('birthday')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    @include($GP247TemplatePath.'.common.render_form_custom_field', ['object' => $customer])

    <button class="btn-primary" type="submit">{{ gp247_language_render('customer.update_infomation') }}</button>
</form>
@endsection
