@php
/*
$layout_page = shop_item_list
**Variables:**
- $subCategory: paginate
Use paginate: $subCategory->appends(request()->except(['page','_token']))->links()
- $itemsList: paginate
Use paginate: $itemsList->appends(request()->except(['page','_token']))->links()
*/ 
@endphp

@extends($GP247TemplatePath.'.layout')

{{-- block_main_content_center --}}
@section('block_main_content_center')
<div class="col-lg-9 col-xl-9">

  @if (count($itemsList))
    <div class="product-top-panel group-md">
      <!-- Render pagination result -->
      @include($GP247TemplatePath.'.common.pagination_result', ['items' => $itemsList])
      <!--// Render pagination result -->
      
      <!-- Render include filter sort -->
      @php
          $view = gp247_shop_process_view($GP247TemplatePath, 'common.shop_product_filter_sort');
      @endphp
      @include($view, ['filterSort' => $filter_sort])
      <!--// Render include filter sort -->
    </div>
    <!-- Item list -->
    <div class="row row-30 row-lg-50">
      @foreach ($itemsList as $key => $item)
      <div class="col-sm-6 col-md-4 col-lg-6 col-xl-4">
          <!-- Render item single -->
          @php
              $view = gp247_shop_process_view($GP247TemplatePath, 'common.shop_item_single');
          @endphp
          @include($view, ['item' => $item])
          <!-- //Render item single -->
        </div>
      @endforeach
    </div>
    <!-- //Item list -->

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