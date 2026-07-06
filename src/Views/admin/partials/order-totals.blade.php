{{--
    Order totals summary (group E, US-SADM-003): the subtotal/tax/shipping/
    discount/other-fee/total rows + the balance, colour-coded by sign (parity
    with the legacy summary). Read-only here (line-item edits drive the totals).
    Variables: $order.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-003
    @aidlc-adr ADR-006, ADR-007
--}}
@php($cur = $order['currency'] ?? '')
@php($balance = (float) ($order['balance'] ?? 0))
@php($balanceCls = $balance < 0 ? 'text-red-600' : ($balance == 0 ? 'text-green-600' : 'text-gray-800 dark:text-gray-100'))
<x-gp247::card :title="gp247_language_render('order.totals.total')">
    <dl class="space-y-3 text-sm">
        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.sub_total') }}</dt><dd class="text-gray-800 dark:text-gray-100">{{ gp247_currency_render($order['subtotal'] ?? 0, '', '', '', false) }}</dd></div>
        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.tax') }}</dt><dd class="text-gray-800 dark:text-gray-100">{{ gp247_currency_render($order['tax'] ?? 0, '', '', '', false) }}</dd></div>
        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.shipping') }}</dt><dd class="text-gray-800 dark:text-gray-100">{{ gp247_currency_render($order['shipping'] ?? 0, '', '', '', false) }}</dd></div>
        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.discount') }}</dt><dd class="text-gray-800 dark:text-gray-100">{{ gp247_currency_render($order['discount'] ?? 0, '', '', '', false) }}</dd></div>
        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.other_fee') }}</dt><dd class="text-gray-800 dark:text-gray-100">{{ gp247_currency_render($order['other_fee'] ?? 0, '', '', '', false) }}</dd></div>
        <div class="flex justify-between border-t border-gray-200 pt-4 font-semibold dark:border-gray-700"><dt class="text-gray-700 dark:text-gray-200">{{ gp247_language_render('order.totals.total') }}</dt><dd class="text-gray-900 dark:text-gray-100">{{ gp247_currency_render($order['total'] ?? 0, '', '', '', false) }}</dd></div>
        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.received') }}</dt><dd class="text-gray-800 dark:text-gray-100">{{ gp247_currency_render($order['received'] ?? 0, '', '', '', false) }}</dd></div>
        <div class="flex justify-between font-bold"><dt class="text-gray-700 dark:text-gray-200">{{ gp247_language_render('order.totals.balance') }}</dt><dd class="{{ $balanceCls }}">{{ gp247_currency_render($balance, '', '', '', false) }}</dd></div>
    </dl>
</x-gp247::card>
