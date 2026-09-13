{{--
    "Recently viewed" sidebar widget — "view"-type layout block (see
    vendor/gp247/front/src/Library/Helpers/front.php::gp247_render_block()).
    Tailwind port of Default/blocks/shop_product_last_view.blade.php: same
    `productsLastView` cookie read (JSON map of product id => last-viewed
    timestamp, newest first), same $modelProduct->start()->getProductFromListID()
    query and gp247_config('product_viewed') limit — no data/PHP logic
    changed, only the markup (compact thumbnail+name+time row, matching the
    `w-20 h-20` thumbnail size already used for cart/wishlist rows).

    Rendered through the admin-assignable layout-block system, like every
    other blocks/*.blade.php view — layout.blade.php's `block_main_content_left`
    unconditionally calls `gp247_render_block('left', $layout_page ?? null)`
    for the `lg:col-span-3` sidebar column on every screen (renders empty,
    harmless, on pages with no matching layout-block row). AppConfig's
    setupStore() assigns this file to position=left, page=shop_product_list
    (Admin > Layout Blocks), the same way blocks/shop_product_home.blade.php
    is assigned to position=bottom, page=front_home. Narrow single-column
    list (not a grid) since `left` is a ~276px sidebar column, next to (not
    below) the product-filter's own `<aside>` — both are independent sidebar
    widgets stacked side by side in that column.

    gp247/shop is optional — guard on the model class before touching
    $modelProduct, same pattern as blocks/shop_product_home.blade.php.

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@if (class_exists(\GP247\Shop\Models\ShopProduct::class))
@php
    $arrProductsLastView = [];
    $lastView = empty(\Cookie::get('productsLastView')) ? [] : json_decode(\Cookie::get('productsLastView'), true);
    if ($lastView) {
        arsort($lastView);
    }
    if ($lastView && count($lastView)) {
        $lastView = array_slice($lastView, 0, gp247_config('product_viewed'), true);
        $productsLastView = $modelProduct->start()->getProductFromListID(array_keys($lastView))->getData();
        foreach ($lastView as $pId => $time) {
            foreach ($productsLastView as $product) {
                if ($product['id'] == $pId) {
                    $product['timelastview'] = $time;
                    $arrProductsLastView[] = $product;
                }
            }
        }
    }
@endphp
@if (!empty($arrProductsLastView))
<div class="card p-4">
    <h3 class="font-semibold text-ink-900 mb-3">{{ gp247_language_render('front.products_last_view') }}</h3>
    <div class="divide-y divide-ink-100">
        @foreach ($arrProductsLastView as $productLastView)
            <a href="{{ $productLastView->getUrl() }}" class="flex gap-3 py-3 first:pt-0 last:pb-0 group">
                <img src="{{ gp247_file($productLastView->getThumb()) }}" alt="{{ $productLastView->name }}" class="w-16 h-16 rounded-lg object-cover shrink-0" loading="lazy">
                <div class="min-w-0">
                    <p class="text-sm text-ink-800 clamp-2 group-hover:text-brand-600">{{ $productLastView->name }}</p>
                    <time datetime="{{ gp247_datetime_to_date($productLastView['timelastview'], 'Y-m-d H:i:s') }}" class="text-xs text-ink-400">
                        {{ gp247_datetime_to_date($productLastView['timelastview'], 'Y-m-d H:i:s') }}
                    </time>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endif
@endif
