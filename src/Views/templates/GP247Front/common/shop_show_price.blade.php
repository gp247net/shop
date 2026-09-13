{{--
    Product-card price display — Tailwind port of
    vendor/gp247/shop/.../front/common/shop_show_price.blade.php. Called by
    $product->showPrice() (ShopProduct.php, unchanged) — used by
    livewire/shop_product-card.blade.php grid cells.

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
<div class="flex items-center gap-2 mt-2">
@switch($kind)
    @case(GP247_PRODUCT_GROUP)
        <span class="price">{!! gp247_language_render('product.price_group') !!}</span>
        @break
    @default
        @if ($price == $priceFinal)
            <span class="price">{!! gp247_currency_render($price) !!}</span>
        @else
            <span class="price">{!! gp247_currency_render($priceFinal) !!}</span>
            <span class="price-old">{!! gp247_currency_render($price) !!}</span>
        @endif
@endswitch
</div>
