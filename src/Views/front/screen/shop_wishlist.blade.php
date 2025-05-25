@php
/*
$layout_page = shop_wishlist
**Variables:**
- $wishlist: no paginate
*/
@endphp
@extends($GP247TemplatePath.'.layout')

@section('block_main_content_center')
<div class="col-lg-9 col-xl-9">
    <h6 class="aside-title">{{ $title }}</h6>
    <div class="container">
        <div class="row">
            <div class="col-md-12 text-danger">
                @if (count($wishlist) ==0)
                    {{ gp247_language_render('front.no_item') }}
                @else
                <div class="table-responsive">
                    <table class="table box table-bordered">
                        <thead>
                            <tr style="background: #eaebec">
                                <th style="width: 50px;">No.</th>
                                <th style="width: 100px;">{{ gp247_language_render('product.sku') }}</th>
                                <th>{{ gp247_language_render('product.name') }}</th>
                                <th>{{ gp247_language_render('product.price') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($wishlist as $item)
                            @php
                            $n = (isset($n)?$n:0);
                            $n++;
                            $product = $modelProduct->start()->getDetail($item->id, null, $item->storeId);
                            @endphp
                            <tr class="row_cart">
                                <td>{{ $n }}</td>
                                <td>{{ $product->sku }}</td>
                                <td>
                                    <a href="{{$product->getUrl() }}" class="row_cart-name">
                                        <img width="100" src="{{gp247_file($product->getImage())}}" alt="{{ $product->name }}">
                                        <span>
                                            {{ $product->name }}<br />
                                            {{-- Process attributes --}}
                                            @if ($item->options)
                                            (
                                            @foreach ($item->options as $keyAtt => $att)
                                            <b>{{ $attributesGroup[$keyAtt] }}</b>: <i>{{ $att }}</i> ;
                                            @endforeach
                                            )<br>
                                            @endif
                                            {{-- //end Process attributes --}}
                                        </span>
                                    </a>
                                </td>
                                <td>{!! $product->showPrice() !!}</td>
                                <td>
                                    <a onClick="return confirm('Confirm')" title="Remove Item" alt="Remove Item"
                                        class="cart_quantity_delete" href="{{ gp247_route_front('cart.remove', ['id'=>$item->rowId, 'instance' => 'wishlist']) }}"><i
                                            class="fa fa-times"></i></a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
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