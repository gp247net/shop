{{--
    Order totals block (L3 presentation) — renders the computed dataTotal lines
    (subtotal, shipping, discount, grand total…). Shared by the default checkout
    view and re-rendered reactively by Livewire when a total-method is applied/removed.

    The #gp247_showTotal id is kept for backward-compat with the legacy AJAX path
    (FrontController::useDiscount html swap); the Livewire re-render is the primary path.

    @aidlc-unit storefront
    @aidlc-story US-LW-006
    @aidlc-adr ADR-storefront-checkout-total-method-contract

    Variables: $dataTotal (array of ['title'=>, 'text'=>]).
--}}
@if (!empty($dataTotal))
    <div class="card p-5" id="gp247_showTotal">
        @foreach ($dataTotal as $total)
            <div class="flex justify-between text-sm py-1">
                <span class="text-ink-500">{{ $total['title'] ?? '' }}</span>
                <span class="font-medium">{{ $total['text'] ?? '' }}</span>
            </div>
        @endforeach
    </div>
@endif
