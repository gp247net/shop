<link rel="stylesheet" href="{{ gp247_file('GP247/Core/AdminShell/vendor/fontawesome-free/css/all.min.css') }}">
<link rel="stylesheet" href="{{ gp247_file('GP247/Core/AdminShell/css/admin.css') }}">

<div class="max-w-4xl mx-auto p-6 text-gray-700">
    <div class="flex items-center justify-between border-b border-gray-200 pb-4 mb-4">
        <img src="{{ gp247_file(gp247_store_info('logo')) }}" style="max-height:60px;">
        <div class="dont-print">
            <button type="button" onclick="order_print()" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <div class="text-center text-2xl font-semibold text-gray-800">{{ gp247_store_info('name') }}</div>

    <hr class="my-4 border-gray-200">

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div class="text-gray-600 space-y-1">
            <div class="font-medium text-gray-800">{{ $name }}</div>
            <div><i class="fas fa-map-marker-alt w-4"></i> {{ $address }}, {{ $country }}</div>
            <div><i class="fas fa-phone-alt w-4"></i> {{ $phone }}</div>
            <div><i class="far fa-envelope w-4"></i> {{ $email }}</div>
        </div>
        <div class="text-gray-600 sm:text-right space-y-1">
            <div><span class="text-gray-500">{{ gp247_language_render('order.id') }}:</span> #{{ $id }}</div>
            <div><span class="text-gray-500">{{ gp247_language_render('order.date') }}:</span> {{ gp247_datetime_to_date($created_at, 'Y-m-d') }}</div>
            <div><span class="text-gray-500">{{ gp247_language_render('order.currency') }}:</span> {{ $currency }}</div>
            <div><span class="text-gray-500">{{ gp247_language_render('order.exchange_rate') }}:</span> {{ $exchange_rate }}</div>
        </div>
    </div>

    <div class="mt-6">
        <div class="grid grid-cols-12 gap-2 bg-gray-700 text-white text-sm font-semibold rounded-t-lg px-3 py-2">
            <div class="hidden sm:block col-span-1">#</div>
            <div class="col-span-7 sm:col-span-5">Description</div>
            <div class="hidden sm:block col-span-2 text-right">Qty</div>
            <div class="hidden sm:block col-span-2 text-right">Unit Price</div>
            <div class="col-span-5 sm:col-span-2 text-right">Amount</div>
        </div>

        <div class="divide-y divide-gray-100 text-sm text-gray-700">
            @foreach ($details as $detail)
            <div class="grid grid-cols-12 gap-2 px-3 py-2">
                <div class="hidden sm:block col-span-1">{{ $detail['no'] }}</div>
                <div class="col-span-7 sm:col-span-5">{{ $detail['name'] }}</div>
                <div class="hidden sm:block col-span-2 text-right">{{ $detail['qty'] }}</div>
                <div class="hidden sm:block col-span-2 text-right">{{ number_format($detail['price']) }}</div>
                <div class="col-span-5 sm:col-span-2 text-right text-gray-800">{{ number_format($detail['total_price']) }}</div>
            </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 mt-4">
            <div class="sm:col-span-7 text-sm text-gray-600 order-2 sm:order-1">
                <i>{!! $comment !!}</i>
            </div>

            <div class="sm:col-span-5 text-sm text-gray-600 order-1 sm:order-2 space-y-2">
                <div class="flex justify-between"><span>{{ gp247_language_render('order.totals.subtotal') }}</span><span class="text-gray-800">{{ gp247_currency_render_symbol($subtotal, $currency) }}</span></div>
                <div class="flex justify-between"><span>{{ gp247_language_render('order.totals.tax') }}</span><span class="text-gray-800">{{ gp247_currency_render_symbol($tax, $currency) }}</span></div>
                <div class="flex justify-between"><span>{{ gp247_language_render('order.totals.shipping') }}</span><span class="text-gray-800">{{ gp247_currency_render_symbol($shipping, $currency) }}</span></div>
                <div class="flex justify-between"><span>{{ gp247_language_render('order.totals.discount') }}</span><span class="text-gray-800">{{ gp247_currency_render_symbol($discount, $currency) }}</span></div>
                <div class="flex justify-between bg-gray-100 rounded-lg px-3 py-2 font-semibold text-gray-900"><span>{{ gp247_language_render('order.totals.total') }}</span><span>{{ gp247_currency_render_symbol($total, $currency) }}</span></div>
                <hr class="border-gray-200">
                <div class="flex justify-between"><span>{{ gp247_language_render('order.other_fee') }}</span><span class="text-gray-800">{{ gp247_currency_render_symbol($other_fee, $currency) }}</span></div>
                <div class="flex justify-between"><span>{{ gp247_language_render('order.totals.received') }}</span><span class="text-gray-800">{{ gp247_currency_render_symbol($received, $currency) }}</span></div>
                <div class="flex justify-between items-center bg-blue-50 rounded-lg px-3 py-2 font-bold text-lg text-green-700"><span class="text-gray-900 text-sm font-semibold">{{ gp247_language_render('order.totals.balance') }}</span><span>{{ gp247_currency_render_symbol($balance, $currency) }}</span></div>
            </div>
        </div>
    </div>
</div>

<script>
  function order_print(){
    document.querySelectorAll('.dont-print').forEach(el => el.style.display = 'none');
    window.print();
    document.querySelectorAll('.dont-print').forEach(el => el.style.display = '');
  }
</script>
