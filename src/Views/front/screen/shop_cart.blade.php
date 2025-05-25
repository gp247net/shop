@php
/*
$layout_page = shop_cart
**Variables:**
- $cart: no paginate
- $countries: array
- $attributesGroup: array
*/
@endphp

@extends($GP247TemplatePath.'.layout')

@section('block_main')
<section class="section section-xl bg-default text-md-left">
    <div class="container">
        <div class="row">
            @if (count($cart) ==0)

            <div class="col-md-12">
                {!! gp247_language_render('cart.cart_empty') !!}!
            </div>

            @else
            @php
                $cartTmp = $cart->groupBy('storeId');
            @endphp

            {{-- Render cart item for earch shop --}}
            @foreach ($cartTmp as $sId => $cartItem)
            <div class="col-md-12">
                <h5><i class="fa fa-shopping-bag" aria-hidden="true"></i>  {{ gp247_store_info(key: 'title', storeId: $sId) }}</h5>
            </div>

            <div class="col-md-12">
                <form action="{{ gp247_route_front('checkout.prepare') }}" method="POST">
                    <input type="hidden" name="store_id" value="{{ $sId }}">
                    @csrf

                    {{-- Item cart detail --}}
                    @php
                        $view = gp247_shop_process_view($GP247TemplatePath, 'common.shop_cart_list');
                    @endphp
                    @include($view, ['cartItem' => $cartItem])
                    {{-- //Item cart detail --}}
                    
                    {{-- Button checkout --}}
                    <div class="col-md-12 text-center">
                        <div class="pull-right">
                            <button class="button button-secondary" type="submit" id="">{{ gp247_language_render('cart.checkout') }}</button>
                        </div>
                    </div>
                    {{-- Button checkout --}}
                </form>
            </div>
            @endforeach
            {{--// Render cart item for earch shop --}}
            @endif
        </div>
    </div>
</section>
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