{{--
    A03 — Order detail (no ecommerce-template demo). Tailwind port of vendor's
    account/shop_order_detail.blade.php, preserving every gp247_config()
    conditional field and the $attributesGroup lookup for product attributes.

    Variables (unchanged from vendor):
    - $statusOrder, $statusShipping: id => label maps
    - $order: ShopOrder (null if not found/not owned -> pageNotFound() already handled by controller)
    - $countries: code => name map
    - $attributesGroup: id => name map

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@php
    $view = gp247_shop_process_view($GP247TemplatePath, 'account.shop_layout');
@endphp
@extends($view)

@section('block_main_profile')
@if (!$order)
    <div class="card p-10 text-center text-ink-500">{{ gp247_language_render('front.no_item') }}</div>
@else
    <div class="grid sm:grid-cols-2 gap-4 mb-6">
        <div class="card p-4">
            <table class="w-full text-sm">
                <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.first_name') }}</td><td class="py-1 font-medium">{!! $order->first_name !!}</td></tr>
                @if (gp247_config('customer_lastname'))
                <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.last_name') }}</td><td class="py-1 font-medium">{!! $order->last_name !!}</td></tr>
                @endif
                @if (gp247_config('customer_phone'))
                <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.phone') }}</td><td class="py-1 font-medium">{!! $order->phone !!}</td></tr>
                @endif
                <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.email') }}</td><td class="py-1 font-medium">{!! empty($order->email) ? 'N/A' : $order->email !!}</td></tr>
                @if (gp247_config('customer_company'))
                <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.company') }}</td><td class="py-1 font-medium">{!! $order->company !!}</td></tr>
                @endif
                @if (gp247_config('customer_postcode'))
                <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.postcode') }}</td><td class="py-1 font-medium">{!! $order->postcode !!}</td></tr>
                @endif
                <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.address1') }}</td><td class="py-1 font-medium">{!! $order->address1 !!}</td></tr>
                @if (gp247_config('customer_address2'))
                <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.address2') }}</td><td class="py-1 font-medium">{!! $order->address2 !!}</td></tr>
                @endif
                @if (gp247_config('customer_address3'))
                <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.address3') }}</td><td class="py-1 font-medium">{!! $order->address3 !!}</td></tr>
                @endif
                @if (gp247_config('customer_country'))
                <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.country') }}</td><td class="py-1 font-medium">{!! $countries[$order->country] ?? $order->country !!}</td></tr>
                @endif
            </table>
        </div>
        <div class="card p-4">
            <table class="w-full text-sm">
                <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.order_status') }}</td><td class="py-1 font-medium"><span class="badge-brand">{{ $statusOrder[$order->status] }}</span></td></tr>
                <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.shipping_status') }}</td><td class="py-1 font-medium">{{ $statusShipping[$order->shipping_status] ?? '' }}</td></tr>
                <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.shipping_method') }}</td><td class="py-1 font-medium">{{ $order->shipping_method }}</td></tr>
                <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.payment_method') }}</td><td class="py-1 font-medium">{{ $order->payment_method }}</td></tr>
                <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.currency') }}</td><td class="py-1 font-medium">{{ $order->currency }}</td></tr>
                <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.exchange_rate') }}</td><td class="py-1 font-medium">{{ $order->exchange_rate ?? 1 }}</td></tr>
            </table>
        </div>
    </div>

    <div class="card overflow-x-auto mb-6">
        <table class="w-full text-sm">
            <thead class="bg-ink-50 text-ink-500 text-xs uppercase">
                <tr>
                    <th class="text-start px-4 py-3">{{ gp247_language_render('product.name') }}</th>
                    <th class="text-start px-4 py-3">{{ gp247_language_render('product.sku') }}</th>
                    <th class="text-end px-4 py-3">{{ gp247_language_render('product.price') }}</th>
                    <th class="text-end px-4 py-3">{{ gp247_language_render('product.quantity') }}</th>
                    <th class="text-end px-4 py-3">{{ gp247_language_render('order.totals.sub_total') }}</th>
                    <th class="text-end px-4 py-3">{{ gp247_language_render('product.tax') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-100">
                @foreach ($order->details as $item)
                    <tr>
                        <td class="px-4 py-3">
                            {{ $item->name }}
                            @php
                                $html = '';
                                if ($item->attribute && is_array(json_decode($item->attribute, true))) {
                                    $array = json_decode($item->attribute, true);
                                    foreach ($array as $key => $element) {
                                        $html .= '<br><span class="text-xs text-ink-400">'.$attributesGroup[$key].': '.$element.'</span>';
                                    }
                                }
                            @endphp
                            {!! $html !!}
                        </td>
                        <td class="px-4 py-3 text-ink-500">{{ $item->sku }}</td>
                        <td class="px-4 py-3 text-end">{{ $item->price }}</td>
                        <td class="px-4 py-3 text-end">x {{ rtrim(rtrim(number_format($item->qty, 2, '.', ''), '0'), '.') }}</td>
                        <td class="px-4 py-3 text-end font-semibold">{{ gp247_currency_render_symbol($item->total_price, $order->currency) }}</td>
                        <td class="px-4 py-3 text-end">{{ $item->tax }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @php
        $dataTotal = \GP247\Shop\Models\ShopOrderTotal::getTotal($order->id);
    @endphp
    <div class="card p-4 max-w-sm ms-auto">
        <table class="w-full text-sm">
            @foreach ($dataTotal as $element)
                @if (in_array($element['code'], ['subtotal', 'tax']))
                    <tr><td class="py-1 text-ink-500">{!! $element['title'] !!}</td><td class="py-1 text-end">{{ gp247_currency_format($element['value']) }}</td></tr>
                @endif
                @if ($element['code'] == 'shipping')
                    <tr><td class="py-1 text-ink-500">{!! $element['title'] !!}</td><td class="py-1 text-end">{{ gp247_currency_format($element['value']) }}</td></tr>
                @endif
                @if ($element['code'] == 'discount')
                    <tr><td class="py-1 text-ink-500">{!! $element['title'] !!}(-)</td><td class="py-1 text-end">{{ gp247_currency_format($element['value']) }}</td></tr>
                @endif
                @if ($element['code'] == 'total')
                    <tr class="font-bold"><td class="py-2 border-t border-ink-100">{!! $element['title'] !!}</td><td class="py-2 text-end border-t border-ink-100">{{ gp247_currency_format($element['value']) }}</td></tr>
                @endif
                @if ($element['code'] == 'received')
                    <tr><td class="py-1 text-ink-500">{!! $element['title'] !!}(-)</td><td class="py-1 text-end">{{ gp247_currency_format($element['value']) }}</td></tr>
                @endif
            @endforeach
            <tr><td class="py-1 text-ink-500">{{ gp247_language_render('order.totals.balance') }}</td><td class="py-1 text-end">{{ gp247_currency_format($order->balance) }}</td></tr>
        </table>
    </div>
@endif
@endsection
