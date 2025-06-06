@php
/*
$layout_page = shop_compare
**Variables:**
- $compare: no paginate
*/
@endphp

@extends($GP247TemplatePath.'.layout')

@section('block_main_content_center')
<div class="col-lg-9 col-xl-9">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h6 class="aside-title">{{ $title }}</h6>
            </div>
            @if (count($compare) ==0)
                <div class="col-md-12 text-danger min-height-37vh">
                    {{ gp247_language_render('front.no_item') }}
                </div>
            @else

            <div class="col-12">
                <div class="table-responsive">
                    <table class="table box table-bordered">
                        <tbody>
                                @php
                                    $n = 0;
                                @endphp

                                @foreach($compare as $key => $item)
                                        @php
                                            $n++;
                                            $product = $modelProduct->start()->getDetail($item->id, null, $item->storeId);
                                        @endphp
                                        @if ($n % 4 == 1)
                                        <tr>
                                        @endif
                                        <td align="center">
                                            {{ $product->name }}({{ $product->sku }})
                                            <hr>
                                            <a href="{{ $product->getUrl() }}"><img width="100"
                                                    src="{{gp247_file($product->getImage())}}" alt=""></a>
                                            <hr>
                                            {!! $product->showPrice() !!}
                                            <hr>
                                            {!! $product->description !!}
                                            <hr>
                                            <a onClick="return confirm('Confirm')" title="Remove Item" alt="Remove Item"
                                                class="cart_quantity_delete"
                                                href="{{ gp247_route_front("cart.remove",['id'=>$item->rowId, 'instance' => 'compare']) }}"><i
                                                    class="fa fa-times"></i></a>
                                        </td>
                                        @if ($n % 4 == 0 || $n == count($compare))
                                        </tr>
                                        @endif
                                @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @endif
            
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