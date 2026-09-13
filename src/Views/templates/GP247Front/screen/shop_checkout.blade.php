{{--
    S05/S06 — Checkout screen (steps 1-3 address/shipping/payment + step 4
    confirm) — Tailwind port of vendor/gp247/shop/.../front/screen/shop_checkout.blade.php.
    Pure wrapper: all wizard behaviour lives in the CheckoutWizard Livewire
    component, overridden at livewire/shop_checkout-wizard.blade.php
    (ADR-010 Hybrid Strangler — step 4 renders a native form POST).

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-010, ADR-011, ADR-014
--}}
@extends($GP247TemplatePath.'.layout')

@section('block_main_content_center')
<div class="lg:col-span-12 w-full max-w-3xl mx-auto">
    @livewire('gp247-shop-front::checkout-wizard')
</div>
@endsection

@push('styles')
@endpush

@push('scripts')
@endpush
