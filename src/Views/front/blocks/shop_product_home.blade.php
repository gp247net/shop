@php
$productsNew = $modelProduct->start()->getProductLatest()->setlimit(gp247_config('product_top'))->getData();
@endphp

@if ($productsNew->count())
      <!-- New Products-->
  <section class="section section-xxl bg-default">
    <div class="container">

        <h2 class="wow fadeScale">{{ gp247_language_render('front.products_new') }}</h2>

        <div class="row row-30 row-lg-50">
        @foreach ($productsNew as $key => $productNew)
        <div class="col-sm-6 col-md-4 col-lg-3">
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
    </section>
@endif