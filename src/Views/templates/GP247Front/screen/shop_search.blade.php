{{--
    S03 — Shop product search screen — Tailwind port of
    vendor/gp247/shop/.../front/screen/shop_search.blade.php.

    Variables:
    - $itemsList: paginate (kept for backward compat; grid served by Livewire)
    - $keyword: string (pre-filled keyword from controller, passed to Livewire component)

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@extends($GP247TemplatePath.'.layout')

@section('block_main_content_center')
<div class="lg:col-span-12 w-full">
    {{-- Product filter + grid (Livewire reactive — US-LW-005) --}}
    @livewire('gp247-shop-front::product-filter', ['initialKeyword' => $keyword ?? ''])
</div>
@endsection

@push('styles')
@endpush

@push('scripts')
@endpush
