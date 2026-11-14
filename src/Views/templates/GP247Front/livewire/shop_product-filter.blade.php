{{--
    Product filter bar + grid (US-LW-003, ADR-011) — Tailwind port of
    vendor/gp247/shop/.../front/livewire/shop_product-filter.blade.php for
    GP247\Shop\Front\Livewire\ProductFilter. All wire:model/wire:click bindings
    kept verbatim (sort/keyword/price min-max/brand toggle/clear).

    Layout: one toolbar card above the grid — row 1 is the in-list search next
    to the sort dropdown (the two controls a shopper reaches for first), row 2
    refines by price and brand. The result count sits on its own line below
    the card so it never has to align with a form control.

    WHY only class combinations that already exist elsewhere in this template:
    the storefront CSS is a pre-built Tailwind bundle (rule gp247 §3b), so a
    brand-new utility here would render unstyled until the bundle is rebuilt.

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-011, ADR-014
--}}
<div wire:loading.class="opacity-50 pointer-events-none" class="transition-opacity">
    @php($currencySymbol = gp247_currency_info()['symbol'] ?? gp247_currency_code())
    <div class="card mb-4" data-testid="shop-product-filter">
        <div class="flex flex-col md:flex-row gap-3 p-4">
            <div class="relative flex-1">
                <svg class="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-ink-400 pointer-events-none rtl-flip" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.35-5.15a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="search" wire:model.live.debounce.500ms="keyword" placeholder="{{ gp247_language_render('front.search') }}" class="input ps-9" data-testid="shop-product-filter-keyword">
            </div>

            {{-- Shopper-facing orders only (ShopProduct::storefrontSort()). The empty value is the
                 default order: the admin's own arrangement, then newest — labelled "Featured"
                 so the dropdown always says what is applied. --}}
            <label class="flex items-center gap-2 shrink-0">
                <span class="text-sm font-medium text-ink-700">{{ gp247_language_render('filter_sort.sort') }}</span>
                <select wire:model.live="sort" class="input w-auto" data-testid="shop-product-filter-sort">
                    <option value="">{{ gp247_language_render('filter_sort.featured') }}</option>
                    <option value="id_desc">{{ gp247_language_render('filter_sort.id_desc') }}</option>
                    <option value="sold_desc">{{ gp247_language_render('filter_sort.best_selling') }}</option>
                    <option value="price_asc">{{ gp247_language_render('filter_sort.price_asc') }}</option>
                    <option value="price_desc">{{ gp247_language_render('filter_sort.price_desc') }}</option>
                    <option value="name_asc">{{ gp247_language_render('filter_sort.name_asc') }}</option>
                </select>
            </label>
        </div>

        <div class="flex flex-wrap items-center gap-x-8 gap-y-4 px-4 py-3 border-t border-ink-100">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-sm font-medium text-ink-700">
                    {{ gp247_language_render('filter_sort.price_range') }}
                    <span class="text-ink-400 font-normal">({{ $currencySymbol }})</span>
                </span>
                <div class="flex items-center gap-2">
                    <input type="number" wire:model="priceMin" wire:keydown.enter="applyPrice" min="0" inputmode="decimal" placeholder="{{ gp247_language_render('filter_sort.price_min') }}" aria-label="{{ gp247_language_render('filter_sort.price_min') }}" class="input w-24 h-10 py-2" data-testid="shop-product-filter-price-min">
                    <span class="text-ink-400">–</span>
                    <input type="number" wire:model="priceMax" wire:keydown.enter="applyPrice" min="0" inputmode="decimal" placeholder="{{ gp247_language_render('filter_sort.price_max') }}" aria-label="{{ gp247_language_render('filter_sort.price_max') }}" class="input w-24 h-10 py-2" data-testid="shop-product-filter-price-max">
                    <button type="button" wire:click="applyPrice" class="btn-primary h-10" data-testid="shop-product-filter-price-apply">{{ gp247_language_render('filter_sort.apply') }}</button>
                </div>
            </div>

            @if ($brands->count())
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-sm font-medium text-ink-700">{{ gp247_language_render('front.brands') }}</span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($brands as $brandItem)
                    @php($brandActive = in_array($brandItem->alias, $selectedBrandAliases))
                    <button type="button"
                        wire:click="toggleBrand('{{ e($brandItem->alias) }}')"
                        aria-pressed="{{ $brandActive ? 'true' : 'false' }}"
                        class="inline-flex items-center gap-1.5 h-9 rounded-full border px-4 text-sm transition {{ $brandActive ? 'border-brand-600 bg-brand-600 text-white font-medium' : 'border-ink-200 bg-white text-ink-700 hover:bg-ink-50' }}"
                        data-testid="shop-product-filter-brand">
                        @if ($brandActive)
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        @endif
                        {{ $brandItem->name }}
                    </button>
                    @endforeach
                </div>
            </div>
            @endif

            @if ($sort !== '' || $price !== '' || $brand !== '' || $keyword !== '')
            <button type="button" wire:click="clearFilters" class="btn-ghost btn-sm ms-auto shrink-0" data-testid="shop-product-filter-clear">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                {{ gp247_language_render('filter_sort.clear') }}
            </button>
            @endif
        </div>
    </div>

    @if ($products->total() > 0)
    @include(gp247_shop_process_view($GP247TemplatePath, 'common.pagination_result'), ['items' => $products])
    @endif

    @if ($products->count())
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
        @foreach ($products as $product)
        @livewire('gp247-shop-front::product-card', ['productId' => $product->id], key('product-card-'.$product->id))
        @endforeach
    </div>

    <div class="mt-8">
        {{ $products->links() }}
    </div>
    @else
    <p class="text-center text-ink-400 py-12">{{ gp247_language_render('front.no_item') }}</p>
    @endif

    <div wire:loading class="fixed inset-0 z-40 pointer-events-none"></div>
</div>
