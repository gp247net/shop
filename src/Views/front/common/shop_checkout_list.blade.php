@php
    $dataCartProcess = gp247_cart_process_data($cartItem);
@endphp
<div class="col-md-12">
  <div class="table-responsive">
      <table class="table box table-bordered">
          <thead>
              <tr style="background: #eaebec">
                  <th>{{ gp247_language_render('product.name') }}</th>
                  <th>{{ gp247_language_render('product.price') }}</th>
                  <th>{{ gp247_language_render('product.quantity') }}</th>
                  <th>{{ gp247_language_render('product.subtotal') }}</th>
              </tr>
          </thead>
          <tbody>
              @foreach($dataCartProcess as $dataProcess)
              <tr class="row_cart form-group {{ session('arrErrorQty')[$dataProcess['process_product_id']] ?? '' }}{{ (session('arrErrorQty')[$dataProcess['process_product_id']] ?? 0) ? ' has-error' : '' }}">
                  <td>
                      <a href="{{$dataProcess['process_product_url'] }}" class="row_cart-name">
                          <img width="100" src="{{gp247_file($dataProcess['process_product_image'])}}"
                              alt="{{ $dataProcess['process_product_name'] }}">
                      </a>
                          <span>
                            <a href="{{$dataProcess['process_product_url'] }}" class="row_cart-name">{{ $dataProcess['process_product_name'] }}</a><br />
                              <b>{{ gp247_language_render('product.sku') }}</b> : {{ $dataProcess['process_product_sku'] }}
                              {!! $dataProcess['process_product_display_vendor'] !!}<br>
                              {{-- Process attributes --}}
                              @if ($dataProcess['process_attributes'])
                              @foreach ($dataProcess['process_attributes'] as $opt)
                              <b>{{ $opt['name'] }}</b>: {!! $opt['value'] !!}
                              @endforeach
                              @endif
                              {{-- //end Process attributes --}}
                          </span>
                      </a>
                  </td>

                  <td>{!! $dataProcess['process_product_show_price'] !!}</td>

                  <td class="cart-col-qty">
                      <div class="cart-qty">
                        {{$dataProcess['process_qty']}}
                      </div>
                  </td>

                  <td align="right">
                    {{ gp247_currency_render($dataProcess['process_product_price_subtotal']) }}
                </td>
              </tr>

              @endforeach
          </tbody>
      </table>
  </div>
</div>


@push('scripts')
@endpush