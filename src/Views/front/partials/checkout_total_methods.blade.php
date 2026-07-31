{{--
    Checkout total-method zone (L3 presentation) — renders the fragment of every
    active total-method plugin (coupon/point) discovered by CheckoutWizard::loadTotalPlugins().
    Template-agnostic: any template (default or future) gets the same behavior by
    including this partial; plugins plug in via CheckoutTotalMethod::checkoutView().

    Each plugin fragment is rendered inside a try/rescue so one broken plugin can
    never take down the checkout (GP247 philosophy §0.3 / NFR-AVAIL).

    Runs inside the CheckoutWizard Livewire component, so the fragment's
    wire:model="totalPayload.<key>.*" / wire:click="applyTotal('<key>')" bind directly.

    @aidlc-unit storefront
    @aidlc-story US-LW-006
    @aidlc-adr ADR-storefront-checkout-total-method-contract

    Variables: $totalPlugins (key => ['info'=>getInfo(), 'view'=>fragment]); $totalMessages, $totalPayload (component state).
--}}
@if (!empty($totalPlugins))
    <div class="space-y-3" id="gp247_total_methods">
        @foreach ($totalPlugins as $totalKey => $totalPlugin)
            @if (!empty($totalPlugin['view']) && view()->exists($totalPlugin['view']))
                @php
                    try {
                        echo $__env->make($totalPlugin['view'], [
                            'pluginKey' => $totalKey,
                            'plugin'    => $totalPlugin['info'] ?? [],
                            'message'   => $totalMessages[$totalKey] ?? null,
                        ])->render();
                    } catch (\Throwable $e) {
                        report($e);
                    }
                @endphp
            @endif
        @endforeach
    </div>
@endif
