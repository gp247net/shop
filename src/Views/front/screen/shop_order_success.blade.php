@php
/*
$layout_page = shop_order_success
**Variables:**
- $orderInfo
*/
@endphp

@extends($GP247TemplatePath.'.layout')

@section('block_main_content_center')
<div class="col-lg-9 col-xl-9">
<h6 class="aside-title">{{ $title }}</h6>
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h2 class="title-page">{{ $title }}</h2>
        </div>
        <div class="col-md-12 text-success">
            <h2>{{ gp247_language_render('checkout.order_success_msg') }}</h2>
            <h3>{{ gp247_language_render('checkout.order_success_order_info', ['order_id'=>session('orderID')]) }}</h3>
        </div>
    </div>
</div>
</div>
@endsection


@push('styles')
      <!-- Render include css cart -->
      @php
          $view = gp247_shop_process_view($GP247TemplatePath, 'common.shop_css');
      @endphp
      @include($view)
      <!--// Render include css cart -->
@endpush

@push('scripts')
@endpush