{{--
    S08 — Wishlist screen — Tailwind port of
    vendor/gp247/shop/.../front/screen/shop_wishlist.blade.php for GP247Front
    (US-TPL-009). No Livewire component backs this screen (route ->
    ShopCartController::wishlistProcessFront() -> plain Blade), so this is a
    server-rendered loop, not a `@livewire()` wrapper like S04/S05. Visual
    grid direction taken from ecommerce-template/wishlist.html's
    `.product-card` grid (its Alpine/mock-data script is discarded — this
    loops the real $wishlist/$modelProduct data instead).

    Variables (unchanged from vendor):
    - $wishlist: Cart content collection (no pagination)
    - $modelProduct: resolved per-row via start()->getDetail()
    - $title: page title

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@extends($GP247TemplatePath.'.layout')

@section('block_main_content_center')
<div class="lg:col-span-12 w-full">
    <h1 class="section-title mb-6">{{ $title }}</h1>

    @if (count($wishlist) == 0)
        <div class="text-center py-20">
            <svg class="w-20 h-20 mx-auto text-ink-200 mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 21s-7-4.35-9.5-8.5C.5 8.5 3 5 6.5 5c1.9 0 3.4 1 5.5 3 2.1-2 3.6-3 5.5-3C21 5 23.5 8.5 21.5 12.5 19 16.65 12 21 12 21z"/></svg>
            <p class="text-lg font-semibold text-ink-700">{{ gp247_language_render('front.no_item') }}</p>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach ($wishlist as $item)
                @php
                    $product = $modelProduct->start()->getDetail($item->id, null, $item->storeId);
                @endphp
                @if ($product)
                <article class="product-card group">
                    <a href="{{ $product->getUrl() }}" class="block relative aspect-square overflow-hidden bg-ink-50">
                        <img src="{{ gp247_file($product->getImage()) }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" loading="lazy">
                    </a>
                    <a href="{{ gp247_route_front('cart.remove', ['id' => $item->rowId, 'instance' => 'wishlist']) }}"
                        onclick="return confirm('{{ e(gp247_language_quickly('cart.confirm_remove', 'Remove this item from the cart?')) }}')"
                        class="absolute top-2 end-2 btn-icon bg-white h-8 w-8 shadow-soft" aria-label="{{ gp247_language_render('action.remove') ?? 'Remove' }}">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </a>

                    <div class="p-3">
                        <a href="{{ $product->getUrl() }}" class="text-sm font-medium text-ink-800 clamp-2 hover:text-brand-600">{{ $product->name }}</a>
                        <p class="text-xs text-ink-400 mt-0.5">{{ gp247_language_render('product.sku') }}: {{ $product->sku }}</p>
                        @if ($item->options)
                            <p class="text-xs text-ink-400">
                                @foreach ($item->options as $keyAtt => $att)
                                    <span>{{ $attributesGroup[$keyAtt] }}: {!! $att !!}</span>@if(!$loop->last) &middot; @endif
                                @endforeach
                            </p>
                        @endif
                        {!! $product->showPrice() !!}
                    </div>
                </article>
                @endif
            @endforeach
        </div>
    @endif
</div>
@endsection

@push('styles')
@endpush

@push('scripts')
@endpush
