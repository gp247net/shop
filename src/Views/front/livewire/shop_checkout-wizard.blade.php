{{--
    CheckoutWizard Livewire component view — Tailwind port of
    vendor/gp247/shop/.../front/livewire/shop_checkout-wizard.blade.php for
    GP247Front (US-TPL-009). Steps 1-3 keep every wire:model/wire:click
    directive unchanged (address/shipping/payment). Step 4 ("confirm") stays a
    plain <form method="POST"> to gp247_route_front('order.add') with only
    @csrf — addOrder() reads all order data from session, not the request
    body, so the Livewire component never renders that step reactively
    (ADR-010 Hybrid Strangler).

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-010, ADR-011, ADR-014
--}}
<div wire:loading.class="opacity-60" class="relative">

    {{-- Step indicator --}}
    <ol class="flex flex-wrap items-center gap-2 mb-8 text-sm">
        @foreach ([
            'address'  => gp247_language_render('cart.checkout'),
            'shipping' => gp247_language_render('cart.shipping_method'),
            'payment'  => gp247_language_render('cart.payment_method'),
            'confirm'  => gp247_language_render('checkout.page_title'),
        ] as $s => $label)
        @php $active = ($step === $s); @endphp
        <li class="flex items-center gap-2">
            <span class="{{ $active ? 'font-semibold text-brand-600' : 'text-ink-400' }} whitespace-nowrap">{{ $label }}</span>
            @if (!$loop->last)<span class="text-ink-300">&rsaquo;</span>@endif
        </li>
        @endforeach
    </ol>

    {{-- Loading overlay --}}
    <div wire:loading class="absolute inset-0 bg-white/50 z-10 rounded-xl"></div>

    {{-- ═══ STEP 1 — ADDRESS ═══ --}}
    @if ($step === 'address')
    <div class="card p-5 sm:p-6" id="cw-address">

        @if ($customer && count($customer->addresses ?? []))
        <div class="mb-5">
            <label class="block text-sm font-medium text-ink-700 mb-1">{{ gp247_language_render('cart.change_address') }}</label>
            <select class="input" wire:change="selectAddress($event.target.value)">
                <option value="">{{ gp247_language_render('cart.change_address') }}</option>
                @foreach ($customer->addresses as $addr)
                <option value="{{ $addr->id }}" @selected($address_process == $addr->id)>
                    {{ $addr->first_name.' '.$addr->last_name.', '.$addr->address1.' '.$addr->address2.' '.$addr->address3 }}
                </option>
                @endforeach
                <option value="new" @selected($address_process === 'new')>{{ gp247_language_render('cart.add_new_address') }}</option>
            </select>
        </div>
        @endif

        @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm px-4 py-3 mb-5">
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
        @endif

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-ink-700 mb-1">{{ gp247_language_render('cart.first_name') }} <span class="text-red-500">*</span></label>
                <input type="text" wire:model="first_name" class="input @error('first_name') input-error @enderror">
                @error('first_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @if ($fieldConfig['last_name']['show'])
            <div>
                <label class="block text-sm font-medium text-ink-700 mb-1">{{ gp247_language_render('cart.last_name') }} @if ($fieldConfig['last_name']['required'])<span class="text-red-500">*</span>@endif</label>
                <input type="text" wire:model="last_name" class="input @error('last_name') input-error @enderror">
                @error('last_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            @if ($fieldConfig['name_kana']['show'])
            <div>
                <label class="block text-sm font-medium text-ink-700 mb-1">{{ gp247_language_render('cart.first_name_kana') }} @if ($fieldConfig['name_kana']['required'])<span class="text-red-500">*</span>@endif</label>
                <input type="text" wire:model="first_name_kana" class="input @error('first_name_kana') input-error @enderror">
                @error('first_name_kana')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink-700 mb-1">{{ gp247_language_render('cart.last_name_kana') }} @if ($fieldConfig['name_kana']['required'])<span class="text-red-500">*</span>@endif</label>
                <input type="text" wire:model="last_name_kana" class="input @error('last_name_kana') input-error @enderror">
                @error('last_name_kana')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-ink-700 mb-1">{{ gp247_language_render('cart.email') }} <span class="text-red-500">*</span></label>
                <input type="email" wire:model="email" class="input @error('email') input-error @enderror">
                @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @if ($fieldConfig['phone']['show'])
            <div>
                <label class="block text-sm font-medium text-ink-700 mb-1">{{ gp247_language_render('cart.phone') }} @if ($fieldConfig['phone']['required'])<span class="text-red-500">*</span>@endif</label>
                <input type="text" wire:model="phone" class="input @error('phone') input-error @enderror">
                @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            @if ($fieldConfig['country']['show'])
            <div>
                <label class="block text-sm font-medium text-ink-700 mb-1">{{ gp247_language_render('cart.country') }} @if ($fieldConfig['country']['required'])<span class="text-red-500">*</span>@endif</label>
                <select wire:model="country" class="input @error('country') input-error @enderror">
                    <option value="">--</option>
                    @foreach ($countries as $code => $name)
                    <option value="{{ $code }}" @selected($country === $code)>{{ $name }}</option>
                    @endforeach
                </select>
                @error('country')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            @if ($fieldConfig['postcode']['show'])
            <div>
                <label class="block text-sm font-medium text-ink-700 mb-1">{{ gp247_language_render('cart.postcode') }} @if ($fieldConfig['postcode']['required'])<span class="text-red-500">*</span>@endif</label>
                <input type="text" wire:model="postcode" class="input @error('postcode') input-error @enderror">
                @error('postcode')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            @if ($fieldConfig['address1']['show'])
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-ink-700 mb-1">{{ gp247_language_render('cart.address1') }} @if ($fieldConfig['address1']['required'])<span class="text-red-500">*</span>@endif</label>
                <input type="text" wire:model="address1" class="input @error('address1') input-error @enderror">
                @error('address1')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            @if ($fieldConfig['address2']['show'])
            <div>
                <label class="block text-sm font-medium text-ink-700 mb-1">{{ gp247_language_render('cart.address2') }} @if ($fieldConfig['address2']['required'])<span class="text-red-500">*</span>@endif</label>
                <input type="text" wire:model="address2" class="input @error('address2') input-error @enderror">
                @error('address2')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            @if ($fieldConfig['address3']['show'])
            <div>
                <label class="block text-sm font-medium text-ink-700 mb-1">{{ gp247_language_render('cart.address3') }} @if ($fieldConfig['address3']['required'])<span class="text-red-500">*</span>@endif</label>
                <input type="text" wire:model="address3" class="input @error('address3') input-error @enderror">
                @error('address3')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            @if ($fieldConfig['company']['show'])
            <div>
                <label class="block text-sm font-medium text-ink-700 mb-1">{{ gp247_language_render('cart.company') }} @if ($fieldConfig['company']['required'])<span class="text-red-500">*</span>@endif</label>
                <input type="text" wire:model="company" class="input @error('company') input-error @enderror">
                @error('company')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-ink-700 mb-1">{{ gp247_language_quickly('cart.comment', 'Comment') }}</label>
                <textarea wire:model="comment" class="input" rows="3"></textarea>
            </div>
        </div>

        <div class="flex justify-end mt-6">
            <button wire:click="nextStep" wire:loading.attr="disabled" class="btn-primary" type="button">
                {{ gp247_language_quickly('cart.next', 'Next') }} &rsaquo;
            </button>
        </div>
    </div>
    @endif

    {{-- ═══ STEP 2 — SHIPPING ═══ --}}
    @if ($step === 'shipping')
    <div class="card p-5 sm:p-6" id="cw-shipping">
        <h2 class="font-semibold mb-4">{{ gp247_language_render('cart.shipping_method') }}</h2>

        @if ($errors->has('shippingMethod'))
        <div class="rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm px-4 py-3 mb-4">{{ $errors->first('shippingMethod') }}</div>
        @endif

        <div class="space-y-3">
            @forelse ($shippingPlugins as $key => $info)
            <label for="sm_{{ $key }}" class="flex items-center gap-3 rounded-lg border border-ink-200 px-4 py-3 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                <input
                    type="radio"
                    name="shippingMethodChoice"
                    id="sm_{{ $key }}"
                    class="accent-brand-600"
                    wire:click="selectShipping('{{ e($key) }}')"
                    @checked($shippingMethod === $key)
                >
                <span class="text-sm">
                    {{ $info['title'] ?? $key }}
                    @if (!empty($info['value']))<span class="text-ink-400"> &ndash; {{ $info['value'] }}</span>@endif
                </span>
            </label>
            @empty
            <p class="text-sm text-ink-400">{{ gp247_language_quickly('cart.no_shipping_method', 'No shipping method available.') }}</p>
            @endforelse
        </div>

        <div class="flex justify-between mt-6">
            <button wire:click="prevStep" class="btn-ghost" type="button">&lsaquo; {{ gp247_language_quickly('action.back', 'Back') }}</button>
            <button wire:click="nextStep" wire:loading.attr="disabled" class="btn-primary" type="button">
                {{ gp247_language_quickly('cart.next', 'Next') }} &rsaquo;
            </button>
        </div>
    </div>
    @endif

    {{-- ═══ STEP 3 — PAYMENT ═══ --}}
    @if ($step === 'payment')
    <div class="card p-5 sm:p-6" id="cw-payment">
        <h2 class="font-semibold mb-4">{{ gp247_language_render('cart.payment_method') }}</h2>

        @if ($errors->has('paymentMethod'))
        <div class="rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm px-4 py-3 mb-4">{{ $errors->first('paymentMethod') }}</div>
        @endif

        <div class="space-y-3">
            @forelse ($paymentPlugins as $key => $info)
            <label for="pm_{{ $key }}" class="flex items-center gap-3 rounded-lg border border-ink-200 px-4 py-3 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                <input
                    type="radio"
                    name="paymentMethodChoice"
                    id="pm_{{ $key }}"
                    class="accent-brand-600"
                    wire:click="selectPayment('{{ e($key) }}')"
                    @checked($paymentMethod === $key)
                >
                <span class="text-sm">{{ $info['title'] ?? $key }}</span>
            </label>
            @empty
            <p class="text-sm text-ink-400">{{ gp247_language_quickly('cart.no_payment_method', 'No payment method available.') }}</p>
            @endforelse
        </div>

        <div class="flex justify-between mt-6">
            <button wire:click="prevStep" class="btn-ghost" type="button">&lsaquo; {{ gp247_language_quickly('action.back', 'Back') }}</button>
            <button wire:click="nextStep" wire:loading.attr="disabled" class="btn-primary" type="button">
                {{ gp247_language_quickly('cart.next', 'Next') }} &rsaquo;
            </button>
        </div>
    </div>
    @endif

    {{-- ═══ STEP 4 — CONFIRM (non-Livewire form POST — ADR-010) ═══ --}}
    @if ($step === 'confirm')
    <div class="space-y-5" id="cw-confirm">
        <h2 class="font-semibold text-lg">{{ gp247_language_render('checkout.page_title') }}</h2>

        <div class="card p-5">
            <h3 class="text-sm font-semibold text-ink-500 mb-2">{{ gp247_language_render('cart.checkout') }}</h3>
            <p class="text-sm text-ink-700 leading-relaxed">
                {{ $first_name }} {{ $last_name }}<br>
                {{ $address1 }}{{ $address2 ? ', '.$address2 : '' }}{{ $address3 ? ', '.$address3 : '' }}<br>
                {{ $country }} {{ $postcode }}<br>
                {{ $email }} / {{ $phone }}
            </p>
        </div>

        @if ($cartItems && count($cartItems))
        <div class="card p-5">
            <h3 class="text-sm font-semibold text-ink-500 mb-3">{{ gp247_language_render('cart.cart_title') }}</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-ink-400 border-b border-ink-100">
                        <th class="py-2 font-normal">{{ gp247_language_quickly('cart.product', 'Product') }}</th>
                        <th class="py-2 font-normal text-right">{{ gp247_language_quickly('cart.qty', 'Qty') }}</th>
                        <th class="py-2 font-normal text-right">{{ gp247_language_quickly('cart.price', 'Price') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($cartItems as $item)
                    <tr>
                        <td class="py-2">
                            {{ $item->name }}
                            @if ($item->options)
                            @foreach ($item->options as $attrId => $attrValue)
                            <br><span class="text-xs text-ink-400">{{ $attributesGroup[$attrId] ?? $attrId }}: {{ $attrValue }}</span>
                            @endforeach
                            @endif
                        </td>
                        <td class="py-2 text-right">{{ $item->qty }}</td>
                        <td class="py-2 text-right price">{{ gp247_currency_format($item->price * $item->qty) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if (!empty($dataTotal))
        <div class="card p-5">
            @foreach ($dataTotal as $total)
            <div class="flex justify-between text-sm py-1">
                <span class="text-ink-500">{{ $total['title'] ?? '' }}</span>
                <span class="font-medium">{{ $total['text'] ?? '' }}</span>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Place order form (non-Livewire POST — addOrder() reads from session, not request body). --}}
        <form method="POST" action="{{ gp247_route_front('order.add') }}">
            @csrf
            {{-- WHY: addOrder() checks request()->all() is truthy before reading session data.
                 A bare @csrf is sufficient — the _token key makes $data non-empty. --}}
            <div class="flex justify-between">
                <button wire:click="prevStep" class="btn-ghost" type="button">&lsaquo; {{ gp247_language_quickly('action.back', 'Back') }}</button>
                <button type="submit" class="btn-primary">
                    {!! gp247_language_quickly('checkout.place_order', 'Place order') !!}
                </button>
            </div>
        </form>
    </div>
    @endif

</div>
