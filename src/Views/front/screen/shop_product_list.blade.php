@php
/*
$layout_page = shop_product_list
**Variables:**
- $subCategory: paginate
Use paginate: $subCategory->appends(request()->except(['page','_token']))->links()
- $products: paginate
Use paginate: $products->appends(request()->except(['page','_token']))->links()
*/ 
@endphp

@extends($GP247TemplatePath.'.layout')

{{-- block_main_content_center --}}
@section('block_main_content_center')
<div class="col-lg-9 col-xl-9">
  {{-- sub category --}}
  @isset ($subCategory)
    @if($subCategory->count())
    <h6 class="aside-title">{{ gp247_language_render('front.sub_categories') }}</h6>
    <div class="row item-folder">
        @foreach ($subCategory as $key => $item)
        <div class="col-6 col-sm-6 col-md-3">
            <div class="item-folder-wrapper product-single">
                <div class="single-products">
                    <div class="productinfo text-center product-box-{{ $item->id }}">
                        <a href="{{ $item->getUrl() }}"><img src="{{ gp247_file($item->getThumb()) }}"
                                alt="{{ $item->title }}" /></a>
                        <a href="{{ $item->getUrl() }}">
                            <p>{{ $item->title }}</p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endforeach

      {{-- Render pagination --}}
      @include($GP247TemplatePath.'.common.pagination', ['items' => $subCategory])
      {{--// Render pagination --}}
      
    </div>
    @endif
  @endisset
  {{-- //sub category --}}

  @if (count($products))
    <div class="product-top-panel group-md">
      <!-- Render pagination result -->
      @include($GP247TemplatePath.'.common.pagination_result', ['items' => $products])
      <!--// Render pagination result -->
      
      <!-- Render include filter sort -->
      @php
          $view = gp247_shop_process_view($GP247TemplatePath, 'common.shop_product_filter_sort');
      @endphp
      @include($view, ['filterSort' => $filter_sort])
      <!--// Render include filter sort -->
    </div>
    <!-- Product list -->
    <div class="row row-30 row-lg-50">
      @foreach ($products as $key => $product)
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
    @include($GP247TemplatePath.'.common.pagination', ['items' => $products])
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