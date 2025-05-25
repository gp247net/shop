@php
/*
$layout_page = shop_search
**Variables:**
- $itemsList: paginate
Use paginate: $itemsList->appends(request()->except(['page','_token']))->links()
*/ 
@endphp

@extends($GP247TemplatePath.'.layout')

{{-- block_main_content_center --}}
@section('block_main_content_center')
<div class="col-lg-9 col-xl-9">

  @if (count($itemsList))
    <!-- Product list -->
    <div class="row row-30 row-lg-50">
      @foreach ($itemsList as $key => $product)
      <div class="col-sm-6 col-md-4 col-lg-6 col-xl-4">
          <!-- Render product single -->
          @php
              $view = gp247_shop_process_view($GP247TemplatePath, 'common.shop_product_single');
          @endphp
          @include($view, ['product' => $product])
          <!-- //Render product single -->
        </div>
      @endforeach
    </div>
    <!-- //Product list -->

    <!-- Render pagination -->
    @include($GP247TemplatePath.'.common.pagination', ['items' => $itemsList])
    <!--// Render pagination -->
  @else
  <div class="product-top-panel group-md">
    <p style="text-align:center">{!! gp247_language_render('front.no_item') !!}</p>
  </div>
  @endif
</div>
@endsection
{{-- //block_main_content_center --}}


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