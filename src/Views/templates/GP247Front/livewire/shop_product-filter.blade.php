{{--
    Product filter bar + grid (US-LW-003, ADR-011) — Tailwind port of
    vendor/gp247/shop/.../front/livewire/shop_product-filter.blade.php for
    GP247\Shop\Front\Livewire\ProductFilter. All wire:model/wire:click bindings
    kept verbatim (sort/keyword/price min-max/brand toggle/clear). Filters
    (search, price range, brand) render as a single full-width bar above the
    product grid rather than a side column, so search/price/brand stay
    together above the fold instead of splitting across a sidebar + content
    layout.

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-011, ADR-014
--}}
<div wire:loading.class="opacity-50 pointer-events-none" class="transition-opacity">
    <div class="card p-4 sm:p-5 mb-6 space-y-4">
        <div class="relative">
            <svg class="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-ink-400 pointer-events-none rtl-flip" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.35-5.15a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="search" wire:model.live.debounce.500ms="keyword" placeholder="{{ gp247_language_render('front.search') }}" class="input ps-9">
        </div>

        <div class="flex flex-wrap items-end gap-x-8 gap-y-4 pt-4 border-t border-ink-100">
            @php($currencySymbol = gp247_currency_info()['symbol'] ?? gp247_currency_code())
            <div class="min-w-[240px]">
                <label class="label">
                    {{ gp247_language_render('filter_sort.price_range') }}
                    <span class="text-ink-400 font-normal">({{ $currencySymbol }})</span>
                </label>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <input type="number" wire:model="priceMin" min="0" placeholder="{{ gp247_language_render('filter_sort.price_min') }}" class="input w-28 pe-7">
                        <span class="absolute end-2.5 top-1/2 -translate-y-1/2 text-ink-400 text-xs pointer-events-none">{{ $currencySymbol }}</span>
                    </div>
                    <span class="text-ink-400">-</span>
                    <div class="relative">
                        <input type="number" wire:model="priceMax" min="0" placeholder="{{ gp247_language_render('filter_sort.price_max') }}" class="input w-28 pe-7">
                        <span class="absolute end-2.5 top-1/2 -translate-y-1/2 text-ink-400 text-xs pointer-events-none">{{ $currencySymbol }}</span>
                    </div>
                    <button type="button" wire:click="applyPrice" class="btn-outline btn-sm shrink-0">{{ gp247_language_render('filter_sort.apply') }}</button>
                </div>
            </div>

            @if ($brands->count())
            <div class="min-w-[200px]">
                <label class="label">{{ gp247_language_render('front.brands') }}</label>
                <div class="flex flex-wrap gap-x-4 gap-y-2 pt-1">
                    @foreach ($brands as $brandItem)
                    <label class="flex items-center gap-1.5 text-sm text-ink-600 cursor-pointer whitespace-nowrap">
                        <input type="checkbox" wire:click="toggleBrand('{{ e($brandItem->alias) }}')" {{ in_array($brandItem->alias, $selectedBrandAliases) ? 'checked' : '' }} class="rounded border-ink-300 accent-brand-600">
                        {{ $brandItem->name }}
                    </label>
                    @endforeach
                </div>
            </div>
            @endif

            @if ($sort !== '' || $price !== '' || $brand !== '' || $keyword !== '')
            <button type="button" wire:click="clearFilters" class="btn-ghost btn-sm ms-auto shrink-0">{{ gp247_language_render('filter_sort.clear') }}</button>
            @endif
        </div>
    </div>

    <div class="flex items-center justify-between mb-4 gap-4">
        @if ($products->total() > 0)
        @include(gp247_shop_process_view($GP247TemplatePath, 'common.pagination_result'), ['items' => $products])
        @endif

        <select wire:model.live="sort" class="input w-auto ms-auto">
            <option value="">{{ gp247_language_render('filter_sort.sort') }}</option>
            <option value="price_asc">{{ gp247_language_render('filter_sort.price_asc') }}</option>
            <option value="price_desc">{{ gp247_language_render('filter_sort.price_desc') }}</option>
            <option value="sort_asc">{{ gp247_language_render('filter_sort.sort_asc') }}</option>
            <option value="sort_desc">{{ gp247_language_render('filter_sort.sort_desc') }}</option>
            <option value="id_asc">{{ gp247_language_render('filter_sort.id_asc') }}</option>
            <option value="id_desc">{{ gp247_language_render('filter_sort.id_desc') }}</option>
        </select>
    </div>

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
