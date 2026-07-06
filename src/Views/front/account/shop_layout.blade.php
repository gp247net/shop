{{--
    Account section layout — sidebar (shop_nav_customer) + content area.
    Vendor's fallback (vendor/gp247/shop/.../front/account/shop_layout.blade.php)
    overrides `block_main` with a Bootstrap container/row/col-md-3/col-md-9
    grid. GP247Front's layout.blade.php instead exposes `block_main_content_center`
    (see layout.blade.php's grid-cols-12 structure used by every prior phase),
    so this overrides that section instead with a Tailwind grid. Child screens
    keep filling `block_main_profile` exactly like the vendor originals — no
    change needed in any A01-A07 file's section name.

    Variables (unchanged from vendor):
    - $title: page title, shown above the content area

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@extends($GP247TemplatePath.'.layout')

@section('block_main_content_center')
<div class="lg:col-span-12 w-full grid grid-cols-1 md:grid-cols-4 gap-6">
    <aside class="md:col-span-1">
        @php
            $view = gp247_shop_process_view($GP247TemplatePath, 'account.shop_nav_customer');
        @endphp
        @include($view)
    </aside>
    <div class="md:col-span-3">
        <h1 class="section-title mb-6">{{ $title }}</h1>
        @section('block_main_profile')
        @show
    </div>
</div>
@endsection
