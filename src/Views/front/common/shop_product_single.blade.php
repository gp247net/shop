<article class="product wow fadeInRight">
    <div class="product-body">
      <div class="product-figure">
          <a href="{{ $product->getUrl() }}">
          <img src="{{ gp247_file($product->getThumb()) }}" alt="{{ $product->name }}"/>
          </a>
      </div>
      <h5 class="product-title"><a href="{{ $product->getUrl() }}">{{ $product->name }}</a></h5>
      
      @if (empty($hiddenStore))
      {!! $product->displayVendor() !!}
      @endif

      @if ($product->allowSale() && gp247_config('product_use_button_add_to_cart'))
      <a onClick="addToCartAjax('{{ $product->id }}','default','{{ $product->store_id }}')" class="button button-secondary button-zakaria add-to-cart-list"><i class="fa fa-cart-plus"></i> {{ gp247_language_render('action.add_to_cart') }}</a>
      @endif

      {!! $product->showPrice() !!}
    </div>
    
    @if ($product->price != $product->getFinalPrice() && $product->kind !=GP247_PRODUCT_GROUP)
    <span><img class="product-badge new" src="{{ gp247_file($GP247TemplateFile.'/images/home/sale.png') }}" class="new" alt="" /></span>
    @elseif($product->kind == GP247_PRODUCT_BUILD)
    <span><img class="product-badge new" src="{{ gp247_file($GP247TemplateFile.'/images/home/bundle.png') }}" class="new" alt="" /></span>
    @elseif($product->kind == GP247_PRODUCT_GROUP)
    <span><img class="product-badge new" src="{{ gp247_file($GP247TemplateFile.'/images/home/group.png') }}" class="new" alt="" /></span>
    @endif
    <div class="product-button-wrap">
      
      @if (gp247_config('product_use_button_wishlist'))
      <div class="product-button">
        <a class="button button-secondary button-zakaria" onClick="addToCartAjax('{{ $product->id }}','wishlist','{{ $product->store_id }}')">
          <i class="fas fa-heart"></i>
        </a>
      </div>
      @endif

      @if (gp247_config('product_use_button_compare'))
      <div class="product-button">
          <a class="button button-primary button-zakaria" onClick="addToCartAjax('{{ $product->id }}','compare','{{ $product->store_id }}')">
              <i class="fa fa-exchange"></i>
          </a>
      </div>
      @endif
    </div>
</article>

@pushOnce('scripts')
      <!-- Render include js cart -->
      @php
          $view = gp247_shop_process_view($GP247TemplatePath, 'common.shop_js');
      @endphp
      @include($view)
      <!--// Render include js cart -->
@endPushOnce