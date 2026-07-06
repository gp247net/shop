{{--
    Product-detail large price display — Tailwind port of
    vendor/gp247/shop/.../front/common/shop_show_price_detail.blade.php.
    Called by $product->showPriceDetail() (ShopProduct.php, unchanged) — used
    by screen/shop_product_detail.blade.php.

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@switch($kind)
    @case(GP247_PRODUCT_GROUP)
        <span class="price text-3xl">{!! gp247_language_render('product.price_group') !!}</span>
        @break
    @default
        @if ($price == $priceFinal)
            <span class="price text-3xl">{!! gp247_currency_render($price) !!}</span>
        @else
            <span class="price text-3xl">{!! gp247_currency_render($priceFinal) !!}</span>
            <span class="price-old text-lg">{!! gp247_currency_render($price) !!}</span>
        @endif
@endswitch
