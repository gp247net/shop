@php
/*
$layout_page = shop_item_list
**Variables:**
- $itemsList: paginate (brand or category root listing — shared by
  ShopBrandController::_allBrands() and ShopCategoryController::_allCategories())
Use paginate: $itemsList->appends(request()->except(['page','_token']))->links()
*/
@endphp

{{--
    Brand / category root listing screen — Tailwind port of
    vendor/gp247/shop/.../front_bk/screen/shop_item_list.blade.php (was left
    on the Bootstrap row/col markup during the initial GP247Front port).
    Reuses common/item_single.blade.php, the same $item['thumb']/['url']/['title']
    contract already used by screen/front_search.blade.php, instead of
    Bootstrap's col-lg-9/row row-30 classes.

    Both ShopCategory (via the joined shop_category_description.name) and
    ShopBrand (plain `name` column) now expose the same `name` attribute, so
    this shared view no longer needs a `title`/`name` fallback.

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@extends($GP247TemplatePath.'.layout')

@section('block_main_content_center')
<div class="lg:col-span-12 w-full">
    @if ($itemsList->count())
        @include($GP247TemplatePath.'.common.pagination_result', ['items' => $itemsList])

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach ($itemsList as $item)
                @php
                    $item['thumb'] = $item->getThumb();
                    $item['url'] = $item->getUrl();
                    $item['title'] = $item->name;
                @endphp
                @include($GP247TemplatePath.'.common.item_single', ['item' => $item])
            @endforeach
        </div>

        @include($GP247TemplatePath.'.common.pagination', ['items' => $itemsList])
    @else
        <p class="text-center text-ink-400 py-12">{{ gp247_language_render('front.no_item') }}</p>
    @endif
</div>
@endsection

@push('styles')
@endpush

@push('scripts')
@endpush
