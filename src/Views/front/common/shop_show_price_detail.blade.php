@switch($kind)
    @case(GP247_PRODUCT_GROUP)
        <span class="gp247-new-price">{!! gp247_language_render('product.price_group') !!}</span>
        @break
    @default
        @if ($price == $priceFinal)
            <span class="gp247-new-price">{!! gp247_currency_render($price) !!}</span>
        @else
            <span class="gp247-new-price">{!! gp247_currency_render($priceFinal) !!}</span>
            <span class="gp247-old-price">{!!  gp247_currency_render($price) !!}</span>
        @endif
@endswitch