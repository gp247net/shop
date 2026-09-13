{{--
    Product card actions (US-LW-004, ADR-015) — Tailwind port of
    vendor/gp247/shop/.../front/livewire/shop_product-card.blade.php for
    GP247\Shop\Front\Livewire\ProductCard (ADR-011 template override, no PHP
    changed). Visual pattern from ecommerce-template/products.html's
    `.product-card` (hover-zoom image, wishlist/compare overlay on hover).

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-011, ADR-014
--}}
<article class="product-card group">
    {{-- $product can be null if it was deleted/deactivated between page load and a Livewire round-trip (component state persists across requests) — keep the root tag outside @if so Livewire always gets a single root element to diff/nest against --}}
    @if ($product)
    <a href="{{ $product->getUrl() }}" class="block relative aspect-square overflow-hidden bg-ink-50">
        <img src="{{ gp247_file($product->getThumb()) }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" loading="lazy">

        @if (gp247_config('product_use_button_wishlist') || gp247_config('product_use_button_compare'))
        <div class="qa absolute top-2 end-2 flex flex-col gap-2">
            @if (gp247_config('product_use_button_wishlist'))
            <a wire:click.prevent="addToCart('wishlist')" wire:loading.attr="disabled" role="button" class="btn-icon bg-white h-8 w-8 shadow-soft" aria-label="{{ gp247_language_render('action.add_to_wishlist') }}">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.35-9.5-8.5C.5 8.5 3 5 6.5 5c1.9 0 3.4 1 5.5 3 2.1-2 3.6-3 5.5-3C21 5 23.5 8.5 21.5 12.5 19 16.65 12 21 12 21z"/></svg>
            </a>
            @endif
            @if (gp247_config('product_use_button_compare'))
            <a wire:click.prevent="addToCart('compare')" wire:loading.attr="disabled" role="button" class="btn-icon bg-white h-8 w-8 shadow-soft" aria-label="{{ gp247_language_render('action.add_to_compare') }}">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3l4 4-4 4M7 21l-4-4 4-4M3 7h14M21 17H7"/></svg>
            </a>
            @endif
        </div>
        @endif
    </a>

    <div class="p-3 flex-1 flex flex-col">
        {!! $product->displayVendor() !!}
        <a href="{{ $product->getUrl() }}" class="text-sm font-medium text-ink-800 clamp-2 mt-0.5 hover:text-brand-600">{{ $product->name }}</a>

        {!! $product->showPrice() !!}

        @if ($product->allowSale() && gp247_config('product_use_button_add_to_cart'))
        <button type="button" wire:click="addToCart('default')" wire:loading.attr="disabled" class="btn-primary btn-sm w-full mt-2">{{ gp247_language_render('action.add_to_cart') }}</button>
        @endif
    </div>
    @endif
</article>
