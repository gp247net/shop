@php
/*
$layout_page = shop_checkout
**Variables:**
- $cart: no paginate
- $shippingMethod: string
- $paymentMethod: string
- $dataTotal: array
- $shippingAddress: array
- $attributesGroup: array
- $products: paginate
Use paginate: $products->appends(request()->except(['page','_token']))->links()
*/
@endphp

@extends($GP247TemplatePath.'.layout')

@section('block_main')
<section class="section section-xl bg-default text-md-left">
    <div class="container">
        <div class="row">
            @if (count($cartItem) ==0)
            <div class="col-md-12 text-danger min-height-37vh">
                {!! gp247_language_render('cart.cart_empty') !!}
            </div>
            @else

            {{-- Item cart detail --}}
            @php
                $view = gp247_shop_process_view($GP247TemplatePath, 'common.shop_checkout_list');
            @endphp
            @include($view, ['cartItem' => $cartItem])
            {{-- //Item cart detail --}}

            <div class="col-12">
                <form class="gp247-shipping-address" id="form-order" role="form" method="POST" action="{{ gp247_route_front('order.add') }}">
                    {{-- Required csrf for secirity --}}
                    @csrf
                    {{--// Required csrf for secirity --}}
                    <div class="row">
                        {{-- Display address --}}
                        <div class="col-12 col-sm-12 col-md-6">
                            <h3 class="control-label"><i class="fa fa-truck" aria-hidden="true"></i>
                                {{ gp247_language_render('cart.shipping_address') }}:<br></h3>
                            <table class="table box table-bordered" id="showTotal">
                                <tr>
                                    <th>{{ gp247_language_render('order.name') }}:</td>
                                    <td>{{ $shippingAddress['first_name'] }} {{ $shippingAddress['last_name'] }}</td>
                                </tr>
                                @if (gp247_config('customer_name_kana'))
                                    <tr>
                                        <th>{{ gp247_language_render('order.name_kana') }}:</td>
                                        <td>{{ $shippingAddress['first_name_kana'].$shippingAddress['last_name_kana'] }}</td>
                                    </tr>
                                @endif

                                @if (gp247_config('customer_phone'))
                                    <tr>
                                        <th>{{ gp247_language_render('order.phone') }}:</td>
                                        <td>{{ $shippingAddress['phone'] }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <th>{{ gp247_language_render('order.email') }}:</td>
                                    <td>{{ $shippingAddress['email'] }}</td>
                                </tr>
                                <tr>
                                    <th>{{ gp247_language_render('order.address') }}:</td>
                                    <td>{{ $shippingAddress['address1'].' '.$shippingAddress['address2'].' '.$shippingAddress['address3'].','.$shippingAddress['country'] }}
                                    </td>
                                </tr>
                                @if (gp247_config('customer_postcode'))
                                    <tr>
                                        <th>{{ gp247_language_render('order.postcode') }}:</td>
                                        <td>{{ $shippingAddress['postcode']}}</td>
                                    </tr>
                                @endif

                                @if (gp247_config('customer_company'))
                                    <tr>
                                        <th>{{ gp247_language_render('order.company') }}:</td>
                                        <td>{{ $shippingAddress['company']}}</td>
                                    </tr>
                                @endif

                                <tr>
                                    <th>{{ gp247_language_render('cart.note') }}:</td>
                                    <td>{{ $shippingAddress['comment'] }}</td>
                                </tr>
                            </table>
                        </div>
                        {{--// Display address --}}

                        <div class="col-12 col-sm-12 col-md-6">
                            {{-- Total --}}
                            <h3 class="control-label"><br></h3>
                            <div class="row">
                                <div class="col-md-12">
                                    <table class="table box table-bordered" id="showTotal">
                                        @foreach ($dataTotal as $key => $element)
                                        @if ($element['code']=='total')
                                        <tr class="showTotal" style="background:#f5f3f3;font-weight: bold;">
                                            <th>{!! $element['title'] !!}</th>
                                            <td style="text-align: right" id="{{ $element['code'] }}">
                                                {{$element['text'] }}
                                            </td>
                                        </tr>
                                        @elseif($element['value'] !=0)
                                        <tr class="showTotal">
                                            <th>{!! $element['title'] !!}</th>
                                            <td style="text-align: right" id="{{ $element['code'] }}">
                                                {{$element['text'] }}
                                            </td>
                                        </tr>
                                        @elseif($element['code'] =='shipping')
                                        <tr class="showTotal">
                                            <th>{!! $element['title'] !!}</th>
                                            <td style="text-align: right" id="{{ $element['code'] }}">
                                                {{$element['text'] }}
                                            </td>
                                        </tr>
                                        @endif
                                        @endforeach
                                    </table>

@if (gp247_config('use_payment'))
                                    {{-- Payment method --}}
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <h3 class="control-label"><i class="fas fa-credit-card"></i>
                                                    {{ gp247_language_render('order.payment_method') }}:<br></h3>
                                            </div>
                                            <div class="form-group">
                                                <div>
                                                    <label class="radio-inline">
                                                        <img title="{{ $paymentMethodData['title'] }}"
                                                            alt="{{ $paymentMethodData['title'] }}"
                                                            src="{{ gp247_file('GP247/'.$paymentMethodData['image']) }}"
                                                            style="width: 120px;">
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    {{-- //Payment method --}}
@endif

                                </div>
                            </div>
                            {{-- End total --}}

                            {{-- Button process cart --}}
                            <div class="row" style="padding-bottom: 20px;">
                                <div class="col-md-12 text-center">
                                    <div class="pull-left">
                                        <button onClick="location.href='{{ gp247_route_front('cart') }}'" class="button button-secondary" type="button" id=""><i class="fa fa-arrow-left"></i>{{ gp247_language_render('cart.back_to_cart') }}</button>
                                    </div>
                                    <div class="pull-right">
                                        <button class="button button-secondary" type="submit" id="">{{ gp247_language_render('cart.confirm') }}</button>
                                    </div>
                                </div>
                            </div>
                            {{--// Button process cart --}}

                        </div>
                    </div>
                </form>
            </div>
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
