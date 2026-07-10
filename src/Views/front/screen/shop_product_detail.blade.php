{{--
    S02 — Product detail screen — Tailwind port of
    vendor/gp247/shop/.../front/screen/shop_product_detail.blade.php.

    Variables:
    - $product: no paginate
    - $productRelation: no paginate

    Gallery: Slick Carousel replaced with a plain Alpine.js thumbnail-switcher
    (x-data="{ activeImage }"), matching ecommerce-template/product.html's
    non-carousel gallery and .claude/rules/ui-tailadmin.md P2 (no jQuery-plugin
    widgets in new/updated UI).

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@extends($GP247TemplatePath.'.layout')

{{--
    JSON-LD Product/BreadcrumbList (US-SEO-005, ADR-014): this screen supplies
    the commerce-specific data ($product, $breadcrumbs); the actual JSON-LD
    building/escaping stays owned by gp247/front (SeoMeta + jsonld_* partials)
    so this package never duplicates that logic.
--}}
@push('jsonld')
    @php
        $jsonldProduct = [
            'name'         => $product->name,
            'url'          => request()->url(),
            'price'        => (string) $product->getFinalPrice(),
            'currency'     => gp247_currency_code(),
            'availability' => $product->allowSale() ? 'InStock' : 'OutOfStock',
            'imageUrl'     => gp247_file($product->getImage()),
            'description'  => $product->description,
        ];
        $breadcrumbItems = array_map(
            fn ($item) => ['name' => $item['title'], 'url' => $item['url'] ?: null],
            $breadcrumbs ?? []
        );
    @endphp
    @include($GP247TemplatePath.'.common.jsonld_product')
    @include($GP247TemplatePath.'.common.jsonld_breadcrumb')
@endpush

@section('block_main_content_center')
<div class="lg:col-span-12 w-full">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Gallery --}}
        @php
            $galleryImages = collect([$product->getImage()])
                ->merge($product->images->map(fn ($image) => $image->getImage()))
                ->map(fn ($path) => gp247_file($path))
                ->values();
        @endphp
        <div x-data="{ activeImage: 0, images: {{ $galleryImages->toJson() }} }">
            <div class="aspect-square rounded-xl overflow-hidden bg-ink-50 mb-3">
                <img :src="images[activeImage]" alt="{{ $product->name }}" class="w-full h-full object-cover">
            </div>
            @if ($galleryImages->count() > 1)
            <div class="grid grid-cols-5 gap-2">
                @foreach ($galleryImages as $key => $imageUrl)
                <button type="button" @click="activeImage = {{ $key }}" class="aspect-square rounded-lg overflow-hidden border-2" :class="activeImage === {{ $key }} ? 'border-brand-600' : 'border-transparent'">
                    <img src="{{ $imageUrl }}" alt="" class="w-full h-full object-cover">
                </button>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Buy form --}}
        <form id="buy_block" action="{{ gp247_route_front('cart.add') }}" method="post">
            {{ csrf_field() }}
            <input type="hidden" name="product_id" id="product-detail-id" value="{{ $product->id }}">

            <h1 class="text-2xl font-semibold text-ink-800" id="product-detail-name">{{ $product->name }}</h1>

            {!! $product->displayVendor() !!}

            <p class="text-sm text-ink-400 mt-1">SKU: <span id="product-detail-model">{{ $product->sku }}</span></p>

            {{-- Show price --}}
            <div class="mt-3" id="product-detail-price">
                {!! $product->showPriceDetail() !!}
            </div>
            {{--// Show price --}}

            <div class="divider my-4"></div>

            {{-- Button add to cart --}}
            @if ($product->kind != GP247_PRODUCT_GROUP && $product->allowSale() && gp247_config('product_use_button_add_to_cart'))
            <div class="flex items-center gap-3">
                <input class="input w-24" name="qty" type="number" value="1" min="{{ gp247_qty_decimal_enabled() ? '0.01' : '1' }}" max="9999" step="{{ gp247_qty_decimal_enabled() ? '0.01' : '1' }}">
                <button class="btn-primary" type="submit" id="gp247-button-process">{{ gp247_language_render('action.add_to_cart') }}</button>
            </div>
            @endif
            {{--// Button add to cart --}}

            {{-- Show attribute --}}
            @if (gp247_config('product_attribute'))
            <div id="product-detail-attr">
                @if ($product->attributes())
                {!! $product->renderAttributeDetails() !!}
                @endif
            </div>
            @endif
            {{--// Show attribute --}}

            {{-- Stock info --}}
            @if (gp247_config('product_stock'))
            <p class="text-sm text-ink-600 mt-4">
                {{ gp247_language_render('product.stock_status') }}:
                <span id="stock_status" class="font-medium">
                    @if ($product->stock <= 0 && !gp247_config('product_buy_out_of_stock'))
                        {{ gp247_language_render('product.out_stock') }}
                    @else
                        {{ gp247_language_render('product.in_stock') }}
                    @endif
                </span>
            </p>
            @endif
            {{--// Stock info --}}

            {{-- date available --}}
            @if (gp247_config('product_available') && $product->date_available >= date('Y-m-d H:i:s'))
            <p class="text-sm text-ink-600 mt-1">
                {{ gp247_language_render('product.date_available') }}:
                <span id="product-detail-available">{{ $product->date_available }}</span>
            </p>
            @endif
            {{--// date available --}}

            {{-- Category info --}}
            <p class="text-sm text-ink-600 mt-4">
                @php
                    $categories = [];
                @endphp
                {{ gp247_language_render('product.category') }}:
                @foreach ($product->categories as $category)
                    @php
                        $categories[] = '<a class="hover:text-brand-600" href="'.$category->getUrl().'">'.$category->getTitle().'</a>';
                    @endphp
                @endforeach
                {!! implode(', ', $categories) !!}
            </p>
            {{--// Category info --}}

            {{-- Brand info --}}
            @if (gp247_config('product_brand') && !empty($product->brand->name))
            <p class="text-sm text-ink-600 mt-1">
                {{ gp247_language_render('product.brand') }}:
                <span id="product-detail-brand">
                    {!! empty($product->brand->name) ? 'None' : '<a class="hover:text-brand-600" href="'.$product->brand->getUrl().'">'.$product->brand->name.'</a>' !!}
                </span>
            </p>
            @endif
            {{--// Brand info --}}

            {{-- Product kind --}}
            @if ($product->kind == GP247_PRODUCT_GROUP)
            <div class="mt-4">
                @php
                    $groups = $product->groups;
                @endphp
                <p class="label mb-2">{{ gp247_language_render('product.kind_group') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($groups as $group)
                    <a target="_blank" href="{{ $group->product->getUrl() }}" class="w-16 h-16 rounded-lg overflow-hidden border">
                        {!! gp247_image_render($group->product->image) !!}
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            @if ($product->kind == GP247_PRODUCT_BUILD)
            <div class="mt-4">
                @php
                    $builds = $product->builds;
                @endphp
                <p class="label mb-2">{{ gp247_language_render('product.kind_bundle') }}</p>
                <div class="rounded-xl border border-ink-100 bg-ink-50/60 p-4">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-4">
                        @foreach ($builds as $key => $build)
                        @if ($key)
                        <span class="text-ink-300 text-lg font-light">+</span>
                        @endif
                        <a target="_blank" href="{{ $build->product->getUrl() }}" class="flex flex-col items-center gap-1.5 w-20 text-center group">
                            <span class="relative w-16 h-16 block">
                                <span class="w-16 h-16 rounded-lg overflow-hidden border bg-white block">{!! gp247_image_render($build->product->image) !!}</span>
                                <span class="absolute -top-1 -end-1 min-w-[18px] h-[18px] px-1 rounded-full bg-brand-600 text-white text-[10px] font-bold flex items-center justify-center">{{ gp247_qty_format($build->quantity) }}</span>
                            </span>
                            <span class="text-xs text-ink-600 clamp-2 group-hover:text-brand-600">{{ $build->product->getName() }}</span>
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
            {{--// Product kind --}}

            <div class="divider my-4"></div>

            {{-- Social --}}
            <div class="flex items-center gap-3">
                <span class="text-sm text-ink-400">Share</span>
                <a href="#" class="btn-icon h-8 w-8" aria-label="Facebook">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M13 22v-9h3l.5-4H13V6.5c0-1.1.3-1.9 2-1.9H17V1.1C16.7 1 15.5 1 14.2 1 11.4 1 9.5 2.7 9.5 5.8V9H6.5v4H9.5v9h3.5z"/></svg>
                </a>
                <a href="#" class="btn-icon h-8 w-8" aria-label="Twitter">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M22 5.9c-.7.3-1.5.6-2.3.7.8-.5 1.5-1.3 1.8-2.3-.8.5-1.7.8-2.6 1a4.1 4.1 0 0 0-7 3.8A11.7 11.7 0 0 1 3.4 4.7a4.1 4.1 0 0 0 1.3 5.5c-.6 0-1.3-.2-1.8-.5v.1c0 2 1.4 3.6 3.3 4a4.1 4.1 0 0 1-1.8.1c.5 1.6 2 2.8 3.8 2.9A8.3 8.3 0 0 1 2 18.6a11.6 11.6 0 0 0 6.3 1.9c7.5 0 11.7-6.3 11.7-11.7v-.5c.8-.6 1.5-1.3 2-2.4z"/></svg>
                </a>
                <a href="#" class="btn-icon h-8 w-8" aria-label="Instagram">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>
                </a>
            </div>
            {{--// Social --}}
        </form>
    </div>

    {{-- Description tab --}}
    <div class="mt-10">
        <div class="border-b border-ink-100 mb-4">
            <span class="nav-link active inline-block pb-2 border-b-2 border-brand-600 text-brand-600">{{ gp247_language_render('product.description') }}</span>
        </div>
        <div class="text-sm text-ink-700 leading-relaxed space-y-3 [&_a]:text-brand-600 [&_img]:rounded-lg [&_img]:max-w-full">
            {!! gp247_html_render($product->content) !!}
        </div>
    </div>
    {{--// Description tab --}}

    @if ($productRelation->count())
    <div class="mt-12">
        <h2 class="section-title mb-4">{{ gp247_language_render('front.products_recommend') }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach ($productRelation as $productRel)
            @livewire('gp247-shop-front::product-card', ['productId' => $productRel->id], key('product-card-'.$productRel->id))
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection

@push('styles')
@endpush

@push('scripts')
@endpush
