{{--
    Update address form. Tailwind port of vendor's account/shop_update_address.blade.php.
    Keeps every gp247_config('customer_*') conditional field and the
    "set as default" checkbox (shown only when this address isn't already default).

    Variables (unchanged from vendor):
    - $customer, $countries, $address

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@php
    $view = gp247_shop_process_view($GP247TemplatePath, 'account.shop_layout');
@endphp
@extends($view)

@section('block_main_profile')
<form method="POST" action="{{ gp247_route_front('customer.post_update_address', ['id' => $address->id]) }}" class="card p-6 max-w-xl space-y-4">
    @csrf
    @if (gp247_config('customer_lastname'))
        <div>
            <label class="label">{{ gp247_language_render('customer.first_name') }}</label>
            <input type="text" class="input @error('first_name') input-error @enderror" name="first_name" required value="{{ old('first_name', $address['first_name']) }}">
            @error('first_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label">{{ gp247_language_render('customer.last_name') }}</label>
            <input type="text" class="input @error('last_name') input-error @enderror" name="last_name" required value="{{ old('last_name', $address['last_name']) }}">
            @error('last_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @else
        <div>
            <label class="label">{{ gp247_language_render('customer.name') }}</label>
            <input type="text" class="input @error('first_name') input-error @enderror" name="first_name" required value="{{ old('first_name', $address['first_name']) }}">
            @error('first_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    @if (gp247_config('customer_phone'))
        <div>
            <label class="label">{{ gp247_language_render('customer.phone') }}</label>
            <input type="text" class="input @error('phone') input-error @enderror" name="phone" required value="{{ old('phone', $address['phone']) }}">
            @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    @if (gp247_config('customer_postcode'))
        <div>
            <label class="label">{{ gp247_language_render('customer.postcode') }}</label>
            <input type="text" class="input @error('postcode') input-error @enderror" name="postcode" required value="{{ old('postcode', $address['postcode']) }}">
            @error('postcode')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    <div>
        <label class="label">{{ gp247_language_render('customer.address1') }}</label>
        <input type="text" class="input @error('address1') input-error @enderror" name="address1" required value="{{ old('address1', $address['address1']) }}">
        @error('address1')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>

    @if (gp247_config('customer_address2'))
        <div>
            <label class="label">{{ gp247_language_render('customer.address2') }}</label>
            <input type="text" class="input @error('address2') input-error @enderror" name="address2" required value="{{ old('address2', $address['address2']) }}">
            @error('address2')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    @if (gp247_config('customer_address3'))
        <div>
            <label class="label">{{ gp247_language_render('customer.address3') }}</label>
            <input type="text" class="input @error('address3') input-error @enderror" name="address3" required value="{{ old('address3', $address['address3']) }}">
            @error('address3')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    @if (gp247_config('customer_country'))
        @php $country = old('country', $address['country']); @endphp
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

    @if ($address->id != $customer->address_id)
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="default" class="rounded">
            {{ gp247_language_render('customer.address_set_default') }}
        </label>
    @endif

    <button class="btn-primary" type="submit">{{ gp247_language_render('customer.update_infomation') }}</button>
</form>
@endsection
