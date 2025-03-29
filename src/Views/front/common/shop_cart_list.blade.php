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
                  <th></th>
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
                          <input style="width: 150px; margin: 0 auto" type="number" data-id="{{ $dataProcess['process_product_id'] }}"
                              data-rowid="{{$dataProcess['process_cart_id']}}" data-store_id="{{$dataProcess['process_store_id']}}" 
                              class="item-qty form-control" name="qty-{{$dataProcess['process_cart_id']}}" value="{{$dataProcess['process_qty']}}">
                      </div>
                      <span class="text-danger item-qty-{{$dataProcess['process_product_id']}}" style="display: none;"></span>
                      @if (session('arrErrorQty')[$dataProcess['process_product_id']] ?? 0)
                      <span class="help-block">
                          <br>{{ gp247_language_render('cart.minimum_value', ['value' => session('arrErrorQty')[$dataProcess['process_product_id']]]) }}
                      </span>
                      @endif
                  </td>

                  <td align="right">
                      {{ gp247_currency_render($dataProcess['process_product_price_subtotal']) }}
                  </td>

                  <td align="center">
                      <a onClick="return confirm('{{ gp247_language_render('cart.confirm_remove_item') }}')" title="{{ gp247_language_render('cart.remove_item') }}" alt="{{ gp247_language_render('cart.remove_item') }}"
                          class="cart_quantity_delete"
                          href="{{ gp247_route_front("cart.remove", ['id'=>$dataProcess['process_cart_id'], 'instance' => 'cart']) }}">
                          <i class="fa fa-times" aria-hidden="true"></i>
                      </a>
                  </td>
              </tr>

              @endforeach
          </tbody>
      </table>
  </div>
</div>


@push('scripts')
<script type="text/javascript">
    function updateCart(obj){
        let new_qty = obj.val();
        let storeId = obj.data('store_id');
        let rowid = obj.data('rowid');
        let id = obj.data('id');
        $.ajax({
            url: '{{ gp247_route_front('cart.update') }}',
            type: 'POST',
            dataType: 'json',
            async: false,
            cache: false,
            data: {
                id: id,
                rowId: rowid,
                new_qty: new_qty,
                storeId: storeId,
                _token:'{{ csrf_token() }}'},
            success: function(data){
                error= parseInt(data.error);
                if(error ===0)
                {
                    window.location.replace(location.href);
                }else{
                    $('.item-qty-'+id).css('display','block').html(data.msg);
                }

                }
        });
    }

    function buttonQty(obj, action){
        var parent = obj.parent();
        var input = parent.find(".item-qty");
        if(action === 'reduce'){
            input.val(parseInt(input.val()) - 1);
        }else{
            input.val(parseInt(input.val()) + 1);
        }
        updateCart(input)
    }
</script>
@endpush