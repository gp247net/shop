{{--
    S07 — Order success screen — no demo source in ecommerce-template/ (its
    checkout.html only toggles an inline "thank you" state, no dedicated
    page); designed fresh in the shared GP247Front visual language, matching
    vendor/gp247/shop/.../front/screen/shop_order_success.blade.php's exact
    variables ($title, session('orderID')) so the existing ShopCartController
    action needs no change.

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@extends($GP247TemplatePath.'.layout')

@section('block_main_content_center')
<div class="lg:col-span-12 w-full max-w-xl mx-auto text-center py-10">
    <div class="w-20 h-20 mx-auto rounded-full bg-emerald-50 flex items-center justify-center mb-6">
        <svg class="w-10 h-10 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"/></svg>
    </div>
    <h1 class="section-title mb-3">{{ $title }}</h1>
    <p class="text-ink-600 mb-2">{{ gp247_language_render('checkout.order_success_msg') }}</p>
    <p class="text-ink-500 mb-8">{{ gp247_language_render('checkout.order_success_order_info', ['order_id' => session('orderID')]) }}</p>
    <a href="{{ gp247_route_front('front.home') }}" class="btn-primary">
        {{ gp247_language_quickly('action.continue_shopping', 'Continue shopping') }}
    </a>
</div>
@endsection

@push('styles')
@endpush

@push('scripts')
@endpush
