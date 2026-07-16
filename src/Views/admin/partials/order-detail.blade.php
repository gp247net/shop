{{--
    Order detail (group E, US-SADM-003): customer info + status workflow + totals
    on the left, items + history on the right. Status selects auto-save on
    change (wire:change → change*Status), parity with the legacy inline editing.
    UI text via gp247_language_render. Variables: $order, $form, $items, $totals,
    $history, $itemForm, $editingItemId, $inputCls.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-003
    @aidlc-adr ADR-005, ADR-006, ADR-007
--}}
<div class="mb-4 flex items-center justify-between">
    <div>
        <x-gp247::button variant="secondary" href="{{ gp247_route_admin('gp247.shop-admin.order') }}" wire:navigate>
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
    {{-- Left: customer + status workflow --}}
    <div class="space-y-6">
        <x-gp247::card :title="gp247_language_render('order.customer')">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.name') }}</dt><dd class="font-medium text-gray-800 dark:text-gray-100">{{ $order['name'] ?? '' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.email') }}</dt><dd class="text-gray-800 dark:text-gray-100">{{ $order['email'] ?? '' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.phone') }}</dt><dd class="text-gray-800 dark:text-gray-100">{{ $order['phone'] ?? '' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.address1') }}</dt><dd class="text-right text-gray-800 dark:text-gray-100">{{ $order['address'] ?? '' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.created_at') }}</dt><dd class="text-gray-800 dark:text-gray-100">{{ $order['created_at'] ?? '' }}</dd></div>
            </dl>
        </x-gp247::card>

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
    </div>

    {{-- Right: items + history --}}
    <div class="space-y-6">
        @include('gp247-shop-admin::partials.order-items', ['inputCls' => $inputCls])
        @include('gp247-shop-admin::partials.order-history')
    </div>
</div>
