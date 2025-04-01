@php
/*
* This template only use for MultiVendor
$layout_page = vendor_home
*/ 
@endphp

@extends($GP247TemplatePath.'.layout')
@php
$productsNew = $modelProduct->start()->getProductLatest()->setlimit(gp247_config('product_top', $storeId))->setStore($storeId)->getData();
@endphp

@section('block_main_content_center')
<div class="col-lg-9 col-xl-9">
      <!-- New Products-->
        <div class="container">
          <h4 class="wow fadeScale">{{ gp247_language_render('front.products_new') }}</h4>
          <div class="row row-30 row-lg-50">
            @foreach ($productsNew as $key => $productNew)
                  {{-- Render product single --}}
                  @php
                      $view = gp247_shop_process_view($GP247TemplatePath, 'common.shop_product_single');
                  @endphp
                  @include($view, ['product' => $productNew])
                  {{-- //Render product single --}}
            </div>
            @endforeach
          </div>
        </div>

        @if (function_exists('gp247_vendor_get_categories_front') &&  count(gp247_vendor_get_categories_front($storeId)))
        @foreach (gp247_vendor_get_categories_front($storeId) as $category)
        <section class="section section-xxl bg-default">
          <div class="container">
                <h4 class="wow fadeScale">{{ $category->title }}</h4>
                <div class="row row-30 row-lg-50">
                  @php
                      $products = $modelProduct->start()->setStore($storeId)->getProductToCategoryStore($category->id)
                      ->setLimit(gp247_config('product_top', $storeId))->getData()
                  @endphp
                  @foreach ($products as $key => $product)
                  <div class="col-sm-6 col-md-4">
                  {{-- Render product single --}}
                  @php
                      $view = gp247_shop_process_view($GP247TemplatePath, 'common.shop_product_single');
                  @endphp
                  @include($view, ['product' => $product])
                  {{-- //Render product single --}}
                  </div>
                  @endforeach
                </div>
          </div>
        </section>
        @endforeach
        @endif
        <!-- Render include view -->
        @include($GP247TemplatePath.'.common.include_view')
        <!--// Render include view -->
</div>
@endsection

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


@push('styles')
{{-- Your css style --}}
@endpush

@push('scripts')
@endpush
