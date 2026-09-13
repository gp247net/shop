{{--
    S04 — Cart screen — Tailwind port of
    vendor/gp247/shop/.../front/screen/shop_cart.blade.php. Pure wrapper: all
    cart behaviour lives in the CartManager Livewire component, overridden at
    livewire/shop_cart-manager.blade.php.

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-011, ADR-014
--}}
@extends($GP247TemplatePath.'.layout')

@section('block_main_content_center')
<div class="lg:col-span-12 w-full">
    @livewire('gp247-shop-front::cart-manager')
</div>
@endsection

@push('styles')
@endpush

@push('scripts')
@endpush
