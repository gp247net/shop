{{--
    Home "shop by category" tiles — "view"-type layout block (see
    vendor/gp247/front/src/Library/Helpers/front.php::gp247_render_block()).
    $modelCategory is shared globally via ShopServiceProvider::boot() (see
    view()->share('modelCategory', ...)). Visual pattern from
    ecommerce-template/index.html's "CATEGORY TILES" section (icon tile +
    label grid); icons are the category's own admin-uploaded image
    (getThumb()) rather than the demo's hardcoded emoji. Only root
    categories flagged "top" are shown (ShopCategory::getCategoryRoot()
    ->getCategoryTop(), same "display on homepage" flag admin.category.top_help
    describes).

    gp247/shop is optional — guard on the model class before touching
    $modelCategory, same pattern as blocks/shop_product_home.blade.php.

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@if (class_exists(\GP247\Shop\Models\ShopCategory::class))
@php
    $topCategories = $modelCategory->start()->getCategoryRoot()->getCategoryTop()->getData();
@endphp
@if ($topCategories->count())
<section class="container-x py-6">
    <h2 class="section-title mb-4">{{ gp247_language_quickly('front.shop_by_category', 'Mua theo danh mục') }}</h2>
    <div class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-8 gap-3 sm:gap-4">
        @foreach ($topCategories as $category)
            <a href="{{ $category->getUrl() }}" class="flex flex-col items-center gap-2 group">
                <span class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-ink-50 flex items-center justify-center overflow-hidden group-hover:bg-brand-50 transition">
                    <img src="{{ gp247_file($category->getThumb()) }}" alt="{{ $category->name }}" class="w-8 h-8 sm:w-9 sm:h-9 object-contain" loading="lazy">
                </span>
                <span class="text-xs text-center text-ink-700 clamp-1">{{ $category->name }}</span>
            </a>
        @endforeach
    </div>
</section>
@endif
@endif
