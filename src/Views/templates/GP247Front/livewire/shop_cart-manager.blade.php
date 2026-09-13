{{--
    CartManager Livewire component view — Tailwind port of
    vendor/gp247/shop/.../front/livewire/shop_cart-manager.blade.php for
    GP247Front (US-TPL-009). Keeps every wire: directive, the
    gp247_cart_process_data() shape, and the checkout.prepare form (with the
    hidden qty-{rowId} inputs ShopCartController::prepareCheckout() needs to
    finalise quantities) unchanged — only the markup/classes are new.

    No coupon / shipping-method chip here (unlike ecommerce-template/cart.html):
    the real CartManager component does not implement those, and the approved
    scope is "no features beyond what Default/the real component supports".

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-011, ADR-014
--}}
<div>
    {{-- Error / success feedback --}}
    @if($errorMessage)
        <div class="rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm px-4 py-3 mb-4 flex items-start justify-between gap-3">
            <span>{!! e($errorMessage) !!}</span>
            <button type="button" class="shrink-0 text-red-500 hover:text-red-700" wire:click="$set('errorMessage', null)" aria-label="Close">&times;</button>
        </div>
    @endif

    @if($successMessage)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 text-sm px-4 py-3 mb-4 flex items-start justify-between gap-3">
            <span>{!! e($successMessage) !!}</span>
            <button type="button" class="shrink-0 text-emerald-600 hover:text-emerald-800" wire:click="$set('successMessage', null)" aria-label="Close">&times;</button>
        </div>
    @endif

    @if($count == 0)
        <div class="text-center py-20">
            <svg class="w-20 h-20 mx-auto text-ink-200 mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3h2l2.4 12.4a2 2 0 0 0 2 1.6h8.6a2 2 0 0 0 2-1.6L22 8H6"/><circle cx="9" cy="21" r="1"/><circle cx="18" cy="21" r="1"/></svg>
            <p class="text-lg font-semibold text-ink-700">{!! gp247_language_render('cart.cart_empty') !!}</p>
        </div>
    @else
        <div class="grid lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-5">
                @foreach($itemsByStore as $storeId => $storeItems)
                    @php
                        $processedItems = gp247_cart_process_data($storeItems);
                        $storeSubtotal  = collect($processedItems)->sum('process_product_price_subtotal');
                    @endphp
                    <div class="card p-4 sm:p-5">
                        <div class="flex items-center justify-between mb-3">
                            <span class="font-semibold text-sm flex items-center gap-2">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l1.5-5h15L21 9M3 9v10a1 1 0 001 1h16a1 1 0 001-1V9M3 9h18M9 13h6"/></svg>
                                {{ gp247_store_info(key: 'name', storeId: $storeId) }}
                            </span>
                            <span class="text-xs text-ink-400">{{ count($processedItems) }} {{ gp247_language_quickly('cart.items', 'items') }}</span>
                        </div>

                        <div class="divide-y divide-ink-100">
                            @foreach($processedItems as $row)
                                <div class="flex gap-3 py-3">
                                    <a href="{{ $row['process_product_url'] }}" class="shrink-0">
                                        <img src="{{ gp247_file($row['process_product_image']) }}" alt="{{ $row['process_product_name'] }}" class="w-20 h-20 rounded-lg object-cover">
                                    </a>
                                    <div class="flex-1 min-w-0">
                                        <a href="{{ $row['process_product_url'] }}" class="text-sm font-medium clamp-2 text-ink-800 hover:text-brand-600">{{ $row['process_product_name'] }}</a>
                                        <p class="text-xs text-ink-400 mt-0.5">
                                            <b>{{ gp247_language_render('product.sku') }}</b>: {{ $row['process_product_sku'] }}
                                            {!! $row['process_product_display_vendor'] !!}
                                        </p>
                                        @if ($row['process_attributes'])
                                        <p class="text-xs text-ink-400">
                                            @foreach ($row['process_attributes'] as $opt)
                                                <span>{{ $opt['name'] }}: {!! $opt['value'] !!}</span>@if(!$loop->last) &middot; @endif
                                            @endforeach
                                        </p>
                                        @endif
                                        <div class="flex items-center justify-between mt-2">
                                            <div class="flex items-center border border-ink-200 rounded-lg">
                                                <button type="button" class="w-8 h-8 text-sm" wire:click="updateQty('{{ $row['process_cart_id'] }}', {{ max(0, $row['process_qty'] - 1) }})" wire:loading.attr="disabled" aria-label="Decrease quantity">&minus;</button>
                                                <input type="number"
                                                    class="w-10 text-center text-sm border-0 focus:ring-0"
                                                    value="{{ gp247_qty_format($row['process_qty']) }}"
                                                    min="0"
                                                    step="{{ gp247_qty_decimal_enabled() ? '0.01' : '1' }}"
                                                    wire:change="updateQty('{{ $row['process_cart_id'] }}', $event.target.value)"
                                                    wire:loading.attr="disabled">
                                                <button type="button" class="w-8 h-8 text-sm" wire:click="updateQty('{{ $row['process_cart_id'] }}', {{ $row['process_qty'] + 1 }})" wire:loading.attr="disabled" aria-label="Increase quantity">+</button>
                                            </div>
                                            <span class="price text-sm">{{ gp247_currency_render($row['process_product_price_subtotal']) }}</span>
                                        </div>
                                    </div>
                                    <button type="button" class="text-ink-300 hover:text-red-500 self-start"
                                        wire:click="removeItem('{{ $row['process_cart_id'] }}')"
                                        wire:loading.attr="disabled"
                                        wire:confirm="{{ e(gp247_language_quickly('cart.confirm_remove', 'Remove this item from the cart?')) }}"
                                        aria-label="{{ gp247_language_render('action.remove') ?? 'Remove' }}">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        <div class="divider my-3"></div>
                        <div class="flex items-center justify-between mb-3">
                            <button type="button" class="btn-ghost btn-sm"
                                wire:click="clearCart"
                                wire:loading.attr="disabled"
                                wire:confirm="{{ e(gp247_language_quickly('cart.confirm_clear', 'Clear all items from the cart?')) }}">
                                {{ gp247_language_quickly('cart.clear_cart', 'Clear cart') }}
                            </button>
                            <span class="text-sm font-semibold text-ink-900">{{ gp247_currency_render($storeSubtotal) }}</span>
                        </div>

                        {{--
                            One checkout form per store (multi-vendor cart: ShopCartController::prepareCheckout()
                            reads a single request('store_id') + qty-{rowId} inputs scoped to that store only —
                            a single combined form across stores would silently drop all but the last store_id).
                        --}}
                        <form action="{{ gp247_route_front('checkout.prepare') }}" method="POST">
                            @csrf
                            <input type="hidden" name="store_id" value="{{ $storeId }}">
                            @foreach($processedItems as $row)
                                <input type="hidden" name="qty-{{ $row['process_cart_id'] }}" value="{{ $row['process_qty'] }}">
                            @endforeach
                            <button type="submit" class="btn-primary w-full">
                                {{ gp247_language_render('cart.checkout') }}
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>

            <div>
                <div class="card p-5 sticky-top">
                    <h2 class="font-semibold mb-4">{{ gp247_language_render('cart.cart_title') ?? gp247_language_render('front.cart') }}</h2>
                    <div class="flex items-center justify-between text-sm font-semibold text-ink-900">
                        <span>{{ gp247_language_quickly('cart.total', 'Total') }}</span>
                        <span>{{ gp247_currency_render(collect($itemsByStore)->flatMap(fn ($storeItems) => gp247_cart_process_data($storeItems))->sum('process_product_price_subtotal')) }}</span>
                    </div>
                    <p class="text-xs text-ink-400 mt-3">{{ gp247_language_quickly('cart.checkout_per_vendor_hint', 'Each vendor is checked out separately.') }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Loading overlay --}}
    <div wire:loading.flex class="w-full justify-center py-2">
        <svg class="animate-spin w-6 h-6 text-ink-300" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
    </div>
</div>
