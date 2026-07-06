{{--
    Home "Flash Sale" strip — "view"-type layout block (see
    vendor/gp247/front/src/Library/Helpers/front.php::gp247_render_block()).
    $modelProduct is shared globally via ShopServiceProvider::boot() (see
    view()->share('modelProduct', ...)). Visual pattern from
    ecommerce-template/index.html's "FLASH SALE" section (title + live
    countdown + view-all link, horizontal snap-scroll product strip).

    Data is real, not decorative: ShopProduct::getProductPromotion() returns
    products with an active ShopProductPromotion (join filters on
    status_promotion/date_start/date_end — see ShopProduct::buildQuery()),
    and the countdown target is the soonest `date_end` among them (ticked
    client-side by gp247frontCountdown in app.js), not a mock timer. Cards
    reuse the shared product-card Livewire component (ADR-015) — this block
    only adds the discount-percent ribbon via ShopProduct::getPercentDiscount(),
    same "wrap + overlay badge" pattern as blocks/shop_product_home.blade.php's
    "new" ribbon.

    gp247/shop is optional — guard on the model class before touching
    $modelProduct, same pattern as blocks/shop_product_home.blade.php.

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@if (class_exists(\GP247\Shop\Models\ShopProduct::class))
@php
    $flashSaleProducts = $modelProduct->start()->getProductPromotion()->getData();
    $flashSaleEndsAt = $flashSaleProducts
        ->pluck('promotionPrice.date_end')
        ->filter()
        ->map(fn ($dateEnd) => \Carbon\Carbon::parse($dateEnd))
        ->min();
@endphp
@if ($flashSaleProducts->count())
<section class="container-x py-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <h2 class="section-title">{{ gp247_language_quickly('front.flash_sale', 'Flash Sale') }}</h2>
            @if ($flashSaleEndsAt)
                <div x-data="gp247frontCountdown({{ $flashSaleEndsAt->getTimestamp() * 1000 }})" class="flex items-center gap-1 text-sm font-mono bg-ink-900 text-white rounded-lg px-3 py-1" aria-label="Countdown">
                    <span x-text="h"></span>:<span x-text="m"></span>:<span x-text="s"></span>
                </div>
            @endif
        </div>
        <a href="{{ gp247_route_front('product.all') }}" class="nav-link">{{ gp247_language_quickly('common.view_all', 'Xem tất cả') }}</a>
    </div>
    <div class="flex gap-4 overflow-x-auto no-scrollbar snap-x pb-2">
        @foreach ($flashSaleProducts as $product)
            <div class="relative snap-start shrink-0 w-[46%] sm:w-[31%] lg:w-[19%]">
                <span class="badge-brand absolute top-2 start-2 z-10">-{{ $product->getPercentDiscount() }}%</span>
                @livewire('gp247-shop-front::product-card', ['productId' => $product->id], key('product-card-'.$product->id))
            </div>
        @endforeach
    </div>
</section>
@endif
@endif
