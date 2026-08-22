{{--
    Order detail (group E, US-SADM-003): customer info + line items (products) on
    the left, order settings (status workflow) + totals + history on the right —
    mirroring the create-order screen layout (order-create.blade.php). Status
    selects auto-save on change (wire:change → change*Status), parity with the
    legacy inline editing.
    The customer card is an edit form (saveOrderInfo — header fields, note and
    payment/shipping method restored by US-SADM-order-info-edit; email stays
    read-only). UI text via gp247_language_render. Variables: $order, $form,
    $items, $totals, $history, $itemForm, $editingItemId, $inputCls.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-003, US-SADM-order-info-edit
    @aidlc-adr ADR-005, ADR-006, ADR-007
--}}
<div class="mb-4 flex items-center justify-between">
    <div>
        <x-gp247::button variant="secondary" href="{{ gp247_route_admin('admin_order.index') }}" wire:navigate>
            <i class="fas fa-arrow-left"></i> {{ gp247_language_render('admin.back') }}
        </x-gp247::button>
        <span class="ml-2 text-sm font-semibold text-gray-700 dark:text-gray-200">#{{ $order['id'] ?? '' }}</span>
    </div>
    <div class="flex items-center gap-2">
        <x-gp247::button variant="ghost" href="{{ $this->invoiceUrl() }}" target="_blank">
            <i class="fas fa-print"></i> {{ gp247_language_render('order.print') }}
        </x-gp247::button>
        <x-gp247::button variant="ghost" wire:click="resendEmail" wire:loading.attr="disabled">
            <i class="fas fa-envelope"></i> {{ gp247_language_render('order.send_mail') }}
        </x-gp247::button>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    {{-- Left: customer info + line items (products) — mirror the create-order screen. --}}
    <div class="space-y-6">
        <x-gp247::card :title="gp247_language_render('order.customer')">
            {{-- Header edit form (US-SADM-order-info-edit) — email is read-only. --}}
            <div class="space-y-3 text-sm">
                @if (gp247_config_admin('customer_email'))
                    <div class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.email') }}</span><span class="text-gray-800 dark:text-gray-100">{{ $order['email'] ?? '' }}</span></div>
                @endif
                <div class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.created_at') }}</span><span class="text-gray-800 dark:text-gray-100">{{ $order['created_at'] ?? '' }}</span></div>

                {{-- Customer fields are gated by the same admin config toggles the
                     create-order screen uses (order-create.blade.php): first_name is
                     always shown, the rest render only when their toggle is on, so
                     both screens stay consistent with the DB config. --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.first_name') }}</label>
                        <input type="text" wire:model="form.first_name" data-testid="shop-admin-order-info-first-name" class="{{ $inputCls }}">
                        @error('form.first_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    @if (gp247_config_admin('customer_lastname'))
                    <div>
                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.last_name') }}</label>
                        <input type="text" wire:model="form.last_name" data-testid="shop-admin-order-info-last-name" class="{{ $inputCls }}">
                        @error('form.last_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    @endif
                    @if (gp247_config_admin('customer_phone'))
                    <div>
                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.phone') }}</label>
                        <input type="text" wire:model="form.phone" data-testid="shop-admin-order-info-phone" class="{{ $inputCls }}">
                        @error('form.phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    @endif
                    @if (gp247_config_admin('customer_company'))
                    <div>
                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.company') }}</label>
                        <input type="text" wire:model="form.company" data-testid="shop-admin-order-info-company" class="{{ $inputCls }}">
                        @error('form.company')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    @endif
                </div>

                {{-- Full address block (QĐ-6): city/district precede address1 to
                     match the canonical order (city -> district -> address1/2/3),
                     then address2/3 and postcode are also editable so the admin can
                     correct any part of the shipping snapshot. Each part is gated by
                     its own config toggle. --}}
                @if (gp247_config_admin('customer_city'))
                <div>
                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.city') }}</label>
                    <input type="text" wire:model="form.city" data-testid="shop-admin-order-info-city" class="{{ $inputCls }}">
                    @error('form.city')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                @endif

                @if (gp247_config_admin('customer_district'))
                <div>
                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.district') }}</label>
                    <input type="text" wire:model="form.district" data-testid="shop-admin-order-info-district" class="{{ $inputCls }}">
                    @error('form.district')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                @endif

                @if (gp247_config_admin('customer_address1'))
                <div>
                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.address1') }}</label>
                    <input type="text" wire:model="form.address1" data-testid="shop-admin-order-info-address1" class="{{ $inputCls }}">
                    @error('form.address1')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                @endif

                @if (gp247_config_admin('customer_address2'))
                <div>
                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.address2') }}</label>
                    <input type="text" wire:model="form.address2" data-testid="shop-admin-order-info-address2" class="{{ $inputCls }}">
                    @error('form.address2')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                @endif

                @if (gp247_config_admin('customer_address3'))
                <div>
                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.address3') }}</label>
                    <input type="text" wire:model="form.address3" data-testid="shop-admin-order-info-address3" class="{{ $inputCls }}">
                    @error('form.address3')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                @endif

                @if (gp247_config_admin('customer_postcode'))
                <div>
                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.postcode') }}</label>
                    <input type="text" wire:model="form.postcode" data-testid="shop-admin-order-info-postcode" class="{{ $inputCls }}">
                    @error('form.postcode')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                @endif

                @if (gp247_config_admin('customer_country'))
                <div>
                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.country') }}</label>
                    <select wire:model="form.country" data-testid="shop-admin-order-info-country" class="{{ $inputCls }}">
                        <option value=""></option>
                        @foreach ($this->countryOptions() as $code => $name)
                            <option value="{{ $code }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('form.country')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                @endif

                {{-- Payment / shipping method are shown only when methods exist,
                     matching the create-order screen (order-create.blade.php gates
                     each block by !empty($paymentMethod)/!empty($shippingMethod)).
                     A stored value keeps its block visible on the edit form so an
                     order placed with a method that was later uninstalled still
                     shows it. --}}
                @php($paymentOptions = $this->paymentMethodOptions())
                @php($shippingOptions = $this->shippingMethodOptions())
                @php($showPayment = !empty($paymentOptions) || ($form['payment_method'] ?? '') !== '')
                @php($showShipping = !empty($shippingOptions) || ($form['shipping_method'] ?? '') !== '')
                @if ($showPayment || $showShipping)
                <div class="grid grid-cols-2 gap-3">
                    @if ($showPayment)
                    <div>
                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.payment_method') }}</label>
                        <select wire:model="form.payment_method" data-testid="shop-admin-order-info-payment-method" class="{{ $inputCls }}">
                            <option value=""></option>
                            {{-- Keep the stored value selectable even when its extension is gone. --}}
                            @if (($form['payment_method'] ?? '') !== '' && !array_key_exists($form['payment_method'], $paymentOptions))
                                <option value="{{ $form['payment_method'] }}">{{ $form['payment_method'] }}</option>
                            @endif
                            @foreach ($paymentOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    @if ($showShipping)
                    <div>
                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.shipping_method') }}</label>
                        <select wire:model="form.shipping_method" data-testid="shop-admin-order-info-shipping-method" class="{{ $inputCls }}">
                            <option value=""></option>
                            @if (($form['shipping_method'] ?? '') !== '' && !array_key_exists($form['shipping_method'], $shippingOptions))
                                <option value="{{ $form['shipping_method'] }}">{{ $form['shipping_method'] }}</option>
                            @endif
                            @foreach ($shippingOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </div>
                @endif

                <div>
                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.note') }}</label>
                    <textarea rows="2" wire:model="form.comment" data-testid="shop-admin-order-info-comment" class="{{ $inputCls }}"></textarea>
                    @error('form.comment')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center justify-end gap-2">
                    <x-gp247::button wire:click="saveOrderInfo" wire:loading.attr="disabled" data-testid="shop-admin-order-info-save">
                        <i class="fas fa-save"></i> {{ gp247_language_render('admin.update') }}
                    </x-gp247::button>
                </div>
            </div>
        </x-gp247::card>

        {{-- Line items (products) sit on the left, directly under the customer
             info — same position as the products block in order-create.blade.php. --}}
        @include('gp247-shop-admin::partials.order-items', ['inputCls' => $inputCls])
    </div>

    {{-- Right: order settings (status workflow) + totals + history. --}}
    <div class="space-y-6">
        <x-gp247::card :title="gp247_language_render('order.status')">
            <div class="space-y-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ gp247_language_render('order.status') }}</label>
                    <select wire:change="changeOrderStatus($event.target.value)" class="{{ $inputCls }}">
                        @foreach ($this->orderStatusOptions() as $id => $name)
                            <option value="{{ $id }}" @selected((int) ($form['status'] ?? 0) === (int) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ gp247_language_render('order.payment_status') }}</label>
                    <select wire:change="changePaymentStatus($event.target.value)" class="{{ $inputCls }}">
                        @foreach ($this->paymentStatusOptions() as $id => $name)
                            <option value="{{ $id }}" @selected((int) ($form['payment_status'] ?? 0) === (int) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ gp247_language_render('order.shipping_status') }}</label>
                    <select wire:change="changeShippingStatus($event.target.value)" class="{{ $inputCls }}">
                        @foreach ($this->shippingStatusOptions() as $id => $name)
                            <option value="{{ $id }}" @selected((int) ($form['shipping_status'] ?? 0) === (int) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-gp247::card>

        @include('gp247-shop-admin::partials.order-totals')
        @include('gp247-shop-admin::partials.order-history')
    </div>
</div>
