{{--
    Home "Promotion products" strip — "view"-type layout block (see
    vendor/gp247/front/src/Library/Helpers/front.php::gp247_render_block()).
    $modelProduct is shared globally via ShopServiceProvider::boot() (see
    view()->share('modelProduct', ...)). Visual pattern from
    ecommerce-template/index.html (title + view-all link, horizontal
    snap-scroll product strip).

    Data is real, not decorative: ShopProduct::getProductPromotion() returns
    products with an active ShopProductPromotion (join filters on
    status_promotion/date_start/date_end — see ShopProduct::buildQuery()).
    Cards reuse the shared product-card Livewire component (ADR-015) — this
    block only adds the discount-percent ribbon via
    ShopProduct::getPercentDiscount(), same "wrap + overlay badge" pattern as
    blocks/shop_product_home.blade.php's "new" ribbon.

    WHY no countdown: this strip is plain price promotions, whose date_end is
    routinely weeks or months out — a timer over it read as a fake urgency cue
    (it showed "1266:51:16"). Time-boxed selling is the separate ProductFlashSale
    plugin (own stock/sold ledger), not this block, so both the timer and the
    "Flash Sale" wording were dropped (modification 20260921T231520). The block
    key/file name stays `shop_flash_sale` on purpose: it is stored in every
    installed site's layout_block.text, and renaming it would make the block
    vanish on upgrade.

    gp247/shop is optional — guard on the model class before touching
    $modelProduct, same pattern as blocks/shop_product_home.blade.php.

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@if (class_exists(\GP247\Shop\Models\ShopProduct::class))
@php
    $promotionProducts = $modelProduct->start()->getProductPromotion()->getData();
@endphp
@if ($promotionProducts->count())
<section class="container-x py-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="section-title">{{ gp247_language_quickly('front.promotion_products', 'Promotion products') }}</h2>
        <a href="{{ gp247_route_front('product.all') }}" class="nav-link">{{ gp247_language_quickly('front.view_all', 'View all') }}</a>
    </div>
    <div class="flex gap-4 overflow-x-auto no-scrollbar snap-x pb-2">
        @foreach ($promotionProducts as $product)
            <div class="relative snap-start shrink-0 w-[46%] sm:w-[31%] lg:w-[19%]">
                <span class="badge-brand absolute top-2 start-2 z-10">-{{ $product->getPercentDiscount() }}%</span>
                @livewire('gp247-shop-front::product-card', ['productId' => $product->id], key('product-card-'.$product->id))
            </div>
        @endforeach
    </div>
</section>
@endif
@endif
