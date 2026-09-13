{{--
    S01 — Product list screen (category / all-products) — Tailwind port of
    vendor/gp247/shop/.../front/screen/shop_product_list.blade.php.

    Variables:
    - $subCategory: paginate (server-rendered, SEO safe)
    - $products: paginate (kept for backward compat; grid served by Livewire)
    - $categoryId: string|null (pins category filter in Livewire component)

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@extends($GP247TemplatePath.'.layout')

@section('block_main_content_center')
<div class="lg:col-span-12 w-full">
    {{-- sub category (server-rendered — crawlable, SEO safe) --}}
    @isset ($subCategory)
        @if ($subCategory->count())
        <h2 class="section-title mb-4">{{ gp247_language_render('front.sub_categories') }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 mb-10">
            @foreach ($subCategory as $item)
            <article class="product-card group">
                <a href="{{ $item->getUrl() }}" class="block relative aspect-square overflow-hidden bg-ink-50">
                    <img src="{{ gp247_file($item->getThumb()) }}" alt="{{ $item->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" loading="lazy">
                </a>
                <div class="p-3">
                    <a href="{{ $item->getUrl() }}" class="text-sm font-medium text-ink-800 clamp-2 hover:text-brand-600">{{ $item->name }}</a>
                </div>
            </article>
            @endforeach
        </div>

        @include($GP247TemplatePath.'.common.pagination', ['items' => $subCategory])
        @endif
    @endisset
    {{-- //sub category --}}

    {{-- Product filter + grid (Livewire reactive — US-LW-005) --}}
    @livewire('gp247-shop-front::product-filter', ['initialCategory' => $categoryId ?? null])
</div>
@endsection

@push('styles')
@endpush

@push('scripts')
@endpush
