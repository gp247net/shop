{{--
    Order totals summary (group E, US-SADM-003): the subtotal/tax/shipping/
    discount/other-fee/total rows + the balance, colour-coded by sign (parity
    with the legacy summary). The shipping/discount/other_fee/received rows are
    editable inline (US-SADM-order-info-edit — Alpine toggle → updateTotalRow,
    raw signed values: discount/received stored negative, "(-)" hint like the
    legacy screen). Subtotal/tax/total stay derived (line items drive them).
    Variables: $order, $totals, $inputCls.

    Money renders in the ORDER's snapshotted currency ($cur = $order->currency)
    via onlyRender (gp247_currency_render_symbol), NOT the active currency — admin
    never runs CurrencyMiddleware so the active static props stay at the class
    default (precision 2). onlyRender formats without re-converting (stored order
    amounts are already in the order currency). US-SADM-order-currency-display.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-003, US-SADM-order-info-edit, US-SADM-order-currency-display
    @aidlc-adr ADR-006, ADR-007, shop_currency-display-precision
--}}
@php($cur = $order['currency'] ?? '')
@php($balance = (float) ($order['balance'] ?? 0))
@php($balanceCls = $balance < 0 ? 'text-red-600' : ($balance == 0 ? 'text-green-600' : 'text-gray-800 dark:text-gray-100'))
@php($rowByCode = collect($totals)->keyBy('code'))
<x-gp247::card :title="gp247_language_render('order.totals.total')">
    <dl class="space-y-3 text-sm">
        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.sub_total') }}</dt><dd class="text-gray-800 dark:text-gray-100">{{ gp247_currency_render_symbol($order['subtotal'] ?? 0, $cur, false, false) }}</dd></div>
        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.tax') }}</dt><dd class="text-gray-800 dark:text-gray-100">{{ gp247_currency_render_symbol($order['tax'] ?? 0, $cur, false, false) }}</dd></div>

        @foreach (['shipping' => false, 'discount' => true, 'other_fee' => false] as $code => $negativeHint)
            @php($row = $rowByCode->get($code))
            @php($label = gp247_language_render('order.totals.' . $code) . ($negativeHint ? ' (-)' : ''))
            @if ($row)
                <div wire:key="total-{{ $code }}" x-data="{ editing: false, val: '{{ (float) $row['value'] }}' }">
                    <div class="flex justify-between" x-show="!editing">
                        <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="text-gray-800 dark:text-gray-100">
                            {{ gp247_currency_render_symbol($order[$code] ?? 0, $cur, false, false) }}
                            <x-gp247::button size="sm" variant="ghost" x-on:click="editing = true; val = '{{ (float) $row['value'] }}'" data-testid="shop-admin-order-total-{{ $code }}"><i class="fas fa-edit"></i></x-gp247::button>
                        </dd>
                    </div>
                    <div x-show="editing" x-cloak>
                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ $label }}</label>
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
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt><dd class="text-gray-800 dark:text-gray-100">{{ gp247_currency_render_symbol($order[$code] ?? 0, $cur, false, false) }}</dd></div>
            @endif
        @endforeach

        <div class="flex justify-between border-t border-gray-200 pt-4 font-semibold dark:border-gray-700"><dt class="text-gray-700 dark:text-gray-200">{{ gp247_language_render('order.totals.total') }}</dt><dd class="text-gray-900 dark:text-gray-100">{{ gp247_currency_render_symbol($order['total'] ?? 0, $cur, false, false) }}</dd></div>

        @foreach (['received' => true] as $code => $negativeHint)
            @php($row = $rowByCode->get($code))
            @php($label = gp247_language_render('order.totals.' . $code) . ($negativeHint ? ' (-)' : ''))
            @if ($row)
                <div wire:key="total-{{ $code }}" x-data="{ editing: false, val: '{{ (float) $row['value'] }}' }">
                    <div class="flex justify-between" x-show="!editing">
                        <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="text-gray-800 dark:text-gray-100">
                            {{ gp247_currency_render_symbol($order[$code] ?? 0, $cur, false, false) }}
                            <x-gp247::button size="sm" variant="ghost" x-on:click="editing = true; val = '{{ (float) $row['value'] }}'" data-testid="shop-admin-order-total-{{ $code }}"><i class="fas fa-edit"></i></x-gp247::button>
                        </dd>
                    </div>
                    <div x-show="editing" x-cloak>
                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ $label }}</label>
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
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt><dd class="text-gray-800 dark:text-gray-100">{{ gp247_currency_render_symbol($order[$code] ?? 0, $cur, false, false) }}</dd></div>
            @endif
        @endforeach

        <div class="flex justify-between font-bold"><dt class="text-gray-700 dark:text-gray-200">{{ gp247_language_render('order.totals.balance') }}</dt><dd class="{{ $balanceCls }}">{{ gp247_currency_render_symbol($balance, $cur, false, false) }}</dd></div>
    </dl>
</x-gp247::card>
