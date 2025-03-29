<div class="product-price-wrap">
@switch($kind)
    @case(GP247_PRODUCT_GROUP)
    <div class="product-price">{!! gp247_language_render('product.price_group') !!}</div>
        @break
    @default
        @if ($price == $priceFinal)
            <div class="product-price">{!! gp247_currency_render($price) !!}</div>
        @else
            <div class="product-price product-price-old">{!!  gp247_currency_render($price) !!}</div>
            <div class="product-price">{!! gp247_currency_render($priceFinal) !!}</div>
        @endif
@endswitch
</div>    