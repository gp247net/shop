{{--
    S09 — Compare screen — no demo page exists in ecommerce-template/
    (confirmed via grep of the whole directory), so this is designed fresh
    per the approved scope decision (modification_analysis_20260703T160000.md
    line 10/81: "tự thiết kế thêm bằng ngôn ngữ thiết kế Tailwind + component
    có sẵn"). Unlike the vendor's 4-per-row raw table
    (vendor/gp247/shop/.../front/screen/shop_compare.blade.php), this renders
    one column per product so shoppers can scan attributes side by side —
    reuses the wishlist screen's product-lookup pattern for parity.

    Variables (unchanged from vendor):
    - $compare: Cart content collection (no pagination)
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

    @if (count($compare) == 0)
        <div class="text-center py-20">
            <svg class="w-20 h-20 mx-auto text-ink-200 mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 3l4 4-4 4M7 21l-4-4 4-4M3 7h14M21 17H7"/></svg>
            <p class="text-lg font-semibold text-ink-700">{{ gp247_language_render('front.no_item') }}</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <div class="flex gap-4 min-w-min pb-2">
                @foreach ($compare as $item)
                    @php
                        $product = $modelProduct->start()->getDetail($item->id, null, $item->storeId);
                    @endphp
                    @if ($product)
                    <div class="card p-4 w-56 shrink-0 flex flex-col">
                        <a href="{{ gp247_route_front('cart.remove', ['id' => $item->rowId, 'instance' => 'compare']) }}"
                            onclick="return confirm('{{ e(gp247_language_quickly('cart.confirm_remove', 'Remove this item from the cart?')) }}')"
                            class="self-end btn-icon h-7 w-7 mb-1" aria-label="{{ gp247_language_render('action.remove') ?? 'Remove' }}">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </a>
                        <a href="{{ $product->getUrl() }}" class="block relative aspect-square overflow-hidden bg-ink-50 rounded-lg mb-3">
                            <img src="{{ gp247_file($product->getImage()) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                        </a>
                        <a href="{{ $product->getUrl() }}" class="text-sm font-medium text-ink-800 clamp-2 hover:text-brand-600">{{ $product->name }}</a>
                        <p class="text-xs text-ink-400 mt-0.5 mb-2">{{ gp247_language_render('product.sku') }}: {{ $product->sku }}</p>
                        {!! $product->showPrice() !!}
                        <div class="divider my-3"></div>
                        <p class="text-xs text-ink-500 line-clamp-6">{!! $product->description !!}</p>
                    </div>
                    @endif
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
