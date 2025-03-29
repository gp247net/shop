@php
/*
* This template only use for MultiVendor
$layout_page = vendor_product_list
**Variables:**
- $products: paginate
Use paginate: $products->appends(request()->except(['page','_token']))->links()
*/ 
@endphp

@extends($GP247TemplatePath.'.layout')

{{-- block_main_content_center --}}
@section('block_main_content_center')
  {{-- Sort filter --}}
  <div class="product-top-panel group-md">

      {{-- Render pagination result --}}
      @include($GP247TemplatePath.'.common.pagination_result', ['items' => $products])
      {{--// Render pagination result --}}

      {{-- Render include filter sort --}}
      @php
          $view = gp247_shop_process_view($GP247TemplatePath, 'common.shop_product_filter_sort');
      @endphp
      @include($view, ['filterSort' => $filter_sort])
      {{--// Render include filter sort --}}
      

  </div>
  {{-- //Sort filter --}}

  {{-- Product list --}}
  <div class="row row-30 row-lg-50">
    @foreach ($products as $key => $product)
    <div class="col-sm-6 col-md-4 col-lg-6 col-xl-4">
          {{-- Render product single --}}
          @php
              $view = gp247_shop_process_view($GP247TemplatePath, 'common.shop_product_single');
          @endphp
          @include($view, ['product' => $product])
          {{-- //Render product single --}}
      </div>
    @endforeach
  </div>
  {{-- //Product list --}}

  {{-- Render pagination --}}
  @includeIf($GP247TemplatePath.'.common.pagination', ['items' => $products])
  {{--// Render pagination --}}

  <!-- Render include view -->
  @include($GP247TemplatePath.'.common.include_view')
  <!--// Render include view -->

@endsection
{{-- //block_main_content_center --}}


@section('blockStoreLeft')
{{-- Categories tore --}}

@if (function_exists('gp247_vendor_get_categories_front') &&  count(gp247_vendor_get_categories_front($storeId)))
<div class="aside-item col-sm-6 col-md-5 col-lg-12">
  <h6 class="aside-title">{{ gp247_language_render('front.categories_store') }}</h6>
  <ul class="list-shop-filter">
    @foreach (gp247_vendor_get_categories_front($storeId) as $category)
    <li class="product-minimal-title active"><a href="{{ $category->getUrl() }}"> {{ $category->title }}</a></li>
    @endforeach
  </ul>
</div>
@endif
{{-- //Categories tore --}}
@endsection


@push('scripts')

@endpush

@push('styles')
{{-- Your css style --}}
@endpush