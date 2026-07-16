{{--
    Home "new arrivals" product grid — "view"-type layout block (see
    vendor/gp247/front/src/Library/Helpers/front.php::gp247_render_block()).
    Tailwind port of vendor/gp247/shop/src/Views/front/blocks/shop_product_home.blade.php
    ($modelProduct is shared globally via ShopServiceProvider::boot(), see
    view()->share('modelProduct', ...)). Grid + product-card usage mirrors
    screen/shop_product_detail.blade.php's "related products" section
    (same `@livewire('gp247-shop-front::product-card', ...)` reuse, ADR-015).
    Visual pattern from ecommerce-template/index.html's "NEW ARRIVALS"
    section (title + view-all link + grid), with a "new" ribbon added per
    card since this block only ever lists the newest products.

    gp247/shop (and its ShopProduct model / product-card Livewire component)
    is optional — a core+front-only install never registers ShopServiceProvider,
    so $modelProduct would not be shared. Guard on the model class before
    touching it.

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@if (class_exists(\GP247\Shop\Models\ShopProduct::class))
@php
    $productsNew = $modelProduct->start()->getProductLatest()->setlimit(gp247_config('product_top'))->getData();
@endphp
@if ($productsNew->count())
<section class="container-x py-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="section-title">{{ gp247_language_render('front.products_new') }}</h2>
        <a href="{{ gp247_route_front('product.all') }}" class="nav-link">{{ gp247_language_quickly('front.view_all', 'View all') }}</a>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        @foreach ($productsNew as $productNew)
            @php
                $badgeKey = match ((int) $productNew->kind) {
                    GP247_PRODUCT_GROUP => 'front.products_group',
                    GP247_PRODUCT_BUILD => 'front.products_bundle',
                    default => 'front.products_new',
                };
            @endphp
            <div class="relative">
                <span class="badge-accent absolute top-2 start-2 z-10 uppercase">{{ gp247_language_render($badgeKey) }}</span>
                @livewire('gp247-shop-front::product-card', ['productId' => $productNew->id], key('product-card-'.$productNew->id))
            </div>
        @endforeach
    </div>
</section>
@endif
@endif
