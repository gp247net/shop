{{--
    Order totals summary (group E, US-SADM-003): the subtotal/tax/shipping/
    discount/other-fee/total rows + the balance, colour-coded by sign (parity
    with the legacy summary). The shipping/discount/other_fee rows are editable
    inline (US-SADM-order-info-edit — Alpine toggle → updateTotalRow).

    Values are MAGNITUDES: every amount is stored non-negative and the minus sign
    is rendered here, from the row's `code`, never read off the data
    (ADR shop-admin_money-sign-convention D1/D4). `received` is NOT a total row and is
    no longer edited as a figure: it is the NET of the order's payment ledger, moved by
    recording a payment or a refund, each keeping its own date/method/gateway reference
    (ADR shop_order-payment-ledger). balance = total − received.
    Subtotal/tax/total stay derived (line items drive them).
    Variables: $order, $totals, $inputCls.

    Money renders in the ORDER's snapshotted currency ($cur = $order->currency)
    via onlyRender (gp247_currency_render_symbol), NOT the active currency — admin
    never runs CurrencyMiddleware so the active static props stay at the class
    default (precision 2). onlyRender formats without re-converting (stored order
    amounts are already in the order currency). US-SADM-order-currency-display.

    The card also shows the order's currency unit (code + name) and its
    exchange_rate snapshot (read-only) at the top, so the admin sees which
    currency the amounts below are in and the rate used at checkout. Both are
    hidden for legacy orders with no currency snapshot. data-testid:
    shop-admin-order-currency, shop-admin-order-exchange-rate.
    Variables: $order (incl. currency, exchange_rate), $totals, $inputCls.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-003, US-SADM-order-info-edit, US-SADM-order-currency-display
    @aidlc-adr ADR-006, ADR-007, shop_currency-display-precision
--}}
@php($cur = $order['currency'] ?? '')
@php($balance = (float) ($order['balance'] ?? 0))
@php($balanceCls = $balance < 0 ? 'text-red-600' : ($balance == 0 ? 'text-green-600' : 'text-gray-800 dark:text-gray-100'))
@php($rowByCode = collect($totals)->keyBy('code'))
{{-- Resolve the order's snapshot currency code to its display name; fall back to
     the bare code so an order in a currency later removed still shows something. --}}
@php($curName = $cur !== '' ? (\GP247\Shop\Models\ShopCurrency::getCodeAll()[$cur] ?? '') : '')
@php($curLabel = $cur !== '' ? ($curName !== '' ? $cur . ' — ' . $curName : $cur) : '')
@php($exchangeRate = (float) ($order['exchange_rate'] ?? 0))
<x-gp247::card :title="gp247_language_render('order.totals.total')">
    <dl class="space-y-3 text-sm">
        {{-- Currency unit + exchange rate of the order (read-only): tells the admin
             which currency every amount below is expressed in and the rate used at
             checkout. Hidden when the order carries no snapshot (legacy orders). --}}
        @if ($cur !== '')
            <div class="flex items-center justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.currency') }}</dt><dd class="text-right text-gray-800 dark:text-gray-100" data-testid="shop-admin-order-currency">{{ $curLabel }}</dd></div>
            @if ($exchangeRate > 0)
                <div class="flex items-center justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.exchange_rate') }}</dt><dd class="text-right text-gray-800 dark:text-gray-100" data-testid="shop-admin-order-exchange-rate">{{ rtrim(rtrim(number_format($exchangeRate, 4, '.', ','), '0'), '.') }}</dd></div>
            @endif
            <div class="border-t border-gray-200 dark:border-gray-700"></div>
        @endif
        <div class="flex items-center justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.sub_total') }}</dt><dd class="text-right text-gray-800 dark:text-gray-100">{{ gp247_currency_render_symbol($order['subtotal'] ?? 0, $cur, false, false) }}</dd></div>
        <div class="flex items-center justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.tax') }}</dt><dd class="text-right text-gray-800 dark:text-gray-100">{{ gp247_currency_render_symbol($order['tax'] ?? 0, $cur, false, false) }}</dd></div>

        {{-- $sign renders the deduction: the stored value is positive, the minus is
             presentation (D4). gp247_currency_render_symbol keeps the order's own
             currency formatting, so the sign is prefixed to the formatted string. --}}
        @foreach (['shipping', 'discount', 'other_fee'] as $code)
            @php($row = $rowByCode->get($code))
            @php($sign = \GP247\Shop\Models\ShopOrderTotal::signOf($code) < 0 ? '-' : '')
            @php($label = gp247_language_render('order.totals.' . $code))
            @php($amount = (float) ($order[$code] ?? 0))
            @if ($row)
                <div wire:key="total-{{ $code }}" x-data="{ editing: false, val: '{{ (float) $row['value'] }}' }">
                    <div class="flex items-center justify-between" x-show="!editing">
                        <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="flex items-center gap-1 text-gray-800 dark:text-gray-100">
                            <x-gp247::button size="sm" variant="ghost"
                                title="{{ gp247_language_render('admin.edit') }} {{ $label }}"
                                x-on:click="editing = true; val = '{{ (float) $row['value'] }}'"
                                data-testid="shop-admin-order-total-{{ $code }}"><i class="fas fa-edit"></i></x-gp247::button>
                            <span class="text-right">{{ ($amount != 0 ? $sign : '') }}{{ gp247_currency_render_symbol($amount, $cur, false, false) }}</span>
                        </dd>
                    </div>
                    <div x-show="editing" x-cloak>
                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ $label }} {{ gp247_money_hint($cur) }}</label>
                        <input type="number" step="0.01" x-model="val" data-testid="shop-admin-order-total-{{ $code }}-input" class="{{ $inputCls }}">
                        <div class="mt-3 flex items-center justify-end gap-2">
                            <x-gp247::button size="sm" variant="secondary" x-on:click="editing = false">{{ gp247_language_render('admin.cancel') }}</x-gp247::button>
                            <x-gp247::button size="sm" x-on:click="$wire.updateTotalRow('{{ $row['id'] }}', val); editing = false" data-testid="shop-admin-order-total-{{ $code }}-save">
                                <i class="fas fa-save"></i> {{ gp247_language_render('admin.update') }}
                            </x-gp247::button>
                        </div>
                    </div>
                </div>
            @else
                <div class="flex items-center justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt><dd class="text-right text-gray-800 dark:text-gray-100">{{ ($amount != 0 ? $sign : '') }}{{ gp247_currency_render_symbol($amount, $cur, false, false) }}</dd></div>
            @endif
        @endforeach

        <div class="flex items-center justify-between border-t border-gray-200 pt-4 font-semibold dark:border-gray-700"><dt class="text-gray-700 dark:text-gray-200">{{ gp247_language_render('order.totals.total') }}</dt><dd class="text-right text-gray-900 dark:text-gray-100">{{ gp247_currency_render_symbol($order['total'] ?? 0, $cur, false, false) }}</dd></div>

        {{-- Received: the NET of the order's payment ledger (Σ payment − Σ refund), not a
             figure anyone edits. Money is added by recording a payment or a refund, so
             every movement keeps its own date, method and gateway reference — which is
             what makes cash-flow-by-payment-date and partial refunds possible
             (ADR shop_order-payment-ledger). --}}
        @php($received = (float) ($order['received'] ?? 0))
        @php($payments = $this->paymentHistory())
        <div wire:key="order-received" x-data="{ open: false, amount: '', paidAt: '', method: '', mode: 'payment' }">
            {{-- Actions first, figure last. With the amount as the final child of a
                 justify-between row its right edge IS the card's right edge, so every
                 figure in this card lines up regardless of how many buttons precede it —
                 no fixed-width spacer, nothing to re-tune when a button is added.

                 Colour carries the direction (money in is green, money out is red) and
                 each button states its action in a tooltip — an unlabelled "+" next to a
                 money figure is exactly the kind of control an admin presses to find out
                 what it does. --}}
            <div class="flex items-center justify-between" x-show="!open">
                <dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.received') }}</dt>
                <dd class="flex items-center gap-1 text-gray-800 dark:text-gray-100">
                    <x-gp247::button size="sm" variant="ghost"
                        title="{{ gp247_language_render('admin.order.record_payment_title') }}"
                        x-on:click="open = true; mode = 'payment'; amount = ''"
                        data-testid="shop-admin-order-record-payment"><i class="fas fa-plus text-green-700"></i></x-gp247::button>
                    @if ($received > 0)
                        <x-gp247::button size="sm" variant="ghost"
                            title="{{ gp247_language_render('admin.order.record_refund_title') }}"
                            x-on:click="open = true; mode = 'refund'; amount = ''"
                            data-testid="shop-admin-order-record-refund"><i class="fas fa-rotate-left text-red-600"></i></x-gp247::button>
                    @endif
                    <span class="text-right" data-testid="shop-admin-order-received">{{ gp247_currency_render_symbol($received, $cur, false, false) }}</span>
                </dd>
            </div>
            {{-- Every field is labelled and says what it is for. Three bare inputs in a
                 column left the admin guessing which one was the amount and which the
                 date, and guessing wrong here writes money. The heading also states the
                 DIRECTION in words, because "+ / -" alone is easy to miss when the same
                 panel serves both recording money and giving it back. --}}
            <div x-show="open" x-cloak class="mt-2 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <div class="mb-3 flex items-center gap-2 border-b border-gray-100 pb-2 dark:border-gray-700">
                    <i class="fas" :class="mode === 'refund' ? 'fa-rotate-left text-red-500' : 'fa-plus text-green-600'"></i>
                    <span class="text-sm font-semibold"
                          :class="mode === 'refund' ? 'text-red-600' : 'text-green-700 dark:text-green-500'"
                          x-text="mode === 'refund'
                              ? '{{ gp247_language_render('admin.order.record_refund_title') }}'
                              : '{{ gp247_language_render('admin.order.record_payment_title') }}'"></span>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">
                            {{ gp247_language_render('admin.order.payment_amount') }}
                            <span class="text-red-500">*</span>
                            <span class="font-normal text-gray-400">{{ gp247_money_hint($cur) }}</span>
                        </label>
                        <input type="number" step="0.01" min="0" x-model="amount"
                               placeholder="0"
                               data-testid="shop-admin-order-payment-amount" class="{{ $inputCls }}">
                        <p class="mt-1 text-xs text-gray-400"
                           x-text="mode === 'refund'
                               ? '{{ gp247_language_render('admin.order.payment_amount_hint_refund') }}'
                               : '{{ gp247_language_render('admin.order.payment_amount_hint') }}'"></p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">
                            {{ gp247_language_render('admin.order.payment_date') }}
                        </label>
                        <input type="date" x-model="paidAt"
                               data-testid="shop-admin-order-payment-date" class="{{ $inputCls }}">
                        <p class="mt-1 text-xs text-gray-400">{{ gp247_language_render('admin.order.payment_date_hint') }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">
                            {{ gp247_language_render('admin.order.payment_method_label') }}
                        </label>
                        <input type="text" x-model="method"
                               placeholder="{{ $order['payment_method'] ?? '' }}"
                               data-testid="shop-admin-order-payment-method" class="{{ $inputCls }}">
                        <p class="mt-1 text-xs text-gray-400">{{ gp247_language_render('admin.order.payment_method_hint') }}</p>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-end gap-2 border-t border-gray-100 pt-3 dark:border-gray-700">
                    <x-gp247::button size="sm" variant="secondary" x-on:click="open = false">{{ gp247_language_render('admin.cancel') }}</x-gp247::button>
                    <x-gp247::button size="sm"
                        x-on:click="mode === 'refund' ? $wire.recordRefund(amount, paidAt, method) : $wire.recordPayment(amount, paidAt, method); open = false"
                        data-testid="shop-admin-order-payment-save">
                        <i class="fas fa-save"></i> {{ gp247_language_render('admin.save') }}
                    </x-gp247::button>
                </div>
            </div>
        </div>

        {{-- Payment history: read-only. A single "received" number could never answer
             "when did the money arrive?" — the question every cash-flow report asks. --}}
        @if (count($payments))
            <div class="border-t border-gray-100 pt-3 dark:border-gray-700" data-testid="shop-admin-order-payment-history">
                <dt class="mb-2 text-xs uppercase tracking-wide text-gray-400">{{ gp247_language_render('order.totals.received') }}</dt>
                {{-- A rule between movements, not just spacing: this list is read by
                     someone checking one specific payment against a bank statement, and
                     rows of similar-looking dates and amounts are easy to slip between.
                     divide-y draws the line only BETWEEN rows, so there is no stray rule
                     under the last one. --}}
                <dd class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($payments as $movement)
                        <div class="flex items-center justify-between gap-2 py-1 text-xs" wire:key="pay-{{ $movement['id'] }}">
                            <span class="text-gray-500 dark:text-gray-400">
                                {{ $movement['paid_at'] }}
                                @if ($movement['method'] !== '')<span class="text-gray-400">· {{ $movement['method'] }}</span>@endif
                                @if ($movement['gateway_transaction_id'] !== '')<span class="text-gray-400">· {{ $movement['gateway_transaction_id'] }}</span>@endif
                            </span>
                            <span class="{{ $movement['type'] === 'refund' ? 'text-red-600' : 'text-green-700 dark:text-green-500' }}">
                                {{ $movement['type'] === 'refund' ? '-' : '+' }}{{ gp247_currency_render_symbol($movement['amount'], $cur, false, false) }}
                            </span>
                        </div>
                    @endforeach
                </dd>
            </div>
        @endif

        <div class="flex items-center justify-between font-bold"><dt class="text-gray-700 dark:text-gray-200">{{ gp247_language_render('order.totals.balance') }}</dt><dd class="text-right {{ $balanceCls }}" data-testid="shop-admin-order-balance">{{ gp247_currency_render_symbol($balance, $cur, false, false) }}</dd></div>
    </dl>
</x-gp247::card>
