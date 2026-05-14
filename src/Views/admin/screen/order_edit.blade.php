@extends('gp247-core::layout')
@php $title = gp247_language_render('order.order_detail') . ' #' . $order->id; @endphp

@section('main')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col">
            <h4>{{ gp247_language_render('order.order_detail') }}: <strong>#{{ $order->id }}</strong></h4>
        </div>
        <div class="col text-right">
            <a href="{{ gp247_route_admin('admin_order.invoice', ['order_id' => $order->id]) }}" target="_blank" class="btn btn-sm btn-danger">
                <i class="far fa-file-pdf"></i> {{ gp247_language_render('order.invoice') }}
            </a>
            <a href="{{ gp247_route_admin('admin_order.index') }}" class="btn btn-sm btn-secondary">
                <i class="fa fa-arrow-left"></i> {{ gp247_language_render('admin.back_list') }}
            </a>
        </div>
    </div>

    <div class="row">
        {{-- Left Column --}}
        <div class="col-md-8">
            
            {{-- Order Information Card --}}
            <div class="card mb-3">
                <div class="card-header font-weight-bold">{{ gp247_language_render('order.order_detail') }}</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>{{ gp247_language_render('order.first_name') }}:</strong> 
                                <a href="#" class="updateInfoRequired" data-name="first_name" data-type="text" data-pk="{{ $order->id }}" data-url="{{ gp247_route_admin('admin_order.post_update') }}" data-title="{{ gp247_language_render('order.first_name') }}">{{ $order->first_name }}</a>
                            </p>
                            
                            @if (gp247_config_admin('customer_lastname'))
                            <p><strong>{{ gp247_language_render('order.last_name') }}:</strong> 
                                <a href="#" class="updateInfoRequired" data-name="last_name" data-type="text" data-pk="{{ $order->id }}" data-url="{{ gp247_route_admin('admin_order.post_update') }}" data-title="{{ gp247_language_render('order.last_name') }}">{{ $order->last_name }}</a>
                            </p>
                            @endif

                            @if (gp247_config_admin('customer_phone'))
                            <p><strong>{{ gp247_language_render('order.phone') }}:</strong> 
                                <a href="#" class="updateInfoRequired" data-name="phone" data-type="text" data-pk="{{ $order->id }}" data-url="{{ gp247_route_admin('admin_order.post_update') }}" data-title="{{ gp247_language_render('order.phone') }}">{{ $order->phone }}</a>
                            </p>
                            @endif

                            <p><strong>{{ gp247_language_render('order.email') }}:</strong> {{ $order->email ?? 'N/A' }}</p>

                            @if (gp247_config_admin('customer_company'))
                            <p><strong>{{ gp247_language_render('order.company') }}:</strong> 
                                <a href="#" class="updateInfoRequired" data-name="company" data-type="text" data-pk="{{ $order->id }}" data-url="{{ gp247_route_admin('admin_order.post_update') }}" data-title="{{ gp247_language_render('order.company') }}">{{ $order->company }}</a>
                            </p>
                            @endif

                            <p><strong>{{ gp247_language_render('order.address1') }}:</strong> 
                                <a href="#" class="updateInfoRequired" data-name="address1" data-type="text" data-pk="{{ $order->id }}" data-url="{{ gp247_route_admin('admin_order.post_update') }}" data-title="{{ gp247_language_render('order.address1') }}">{{ $order->address1 }}</a>
                            </p>

                            @if (gp247_config_admin('customer_country'))
                            <p><strong>{{ gp247_language_render('order.country') }}:</strong> 
                                <a href="#" class="updateInfoRequired" data-name="country" data-type="select" data-source="{{ json_encode($country) }}" data-pk="{{ $order->id }}" data-url="{{ gp247_route_admin('admin_order.post_update') }}" data-title="{{ gp247_language_render('order.country') }}" data-value="{{ $order->country }}"></a>
                            </p>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <p><strong>{{ gp247_language_render('order.order_status') }}:</strong> 
                                <a href="#" class="updateStatus" data-name="status" data-type="select" data-source="{{ json_encode($statusOrder) }}" data-pk="{{ $order->id }}" data-value="{{ $order->status }}" data-url="{{ gp247_route_admin('admin_order.post_update') }}" data-title="{{ gp247_language_render('order.order_status') }}">{{ $statusOrder[$order->status] ?? $order->status }}</a>
                            </p>

                            <p><strong>{{ gp247_language_render('order.shipping_status') }}:</strong> 
                                <a href="#" class="updateStatus" data-name="shipping_status" data-type="select" data-source="{{ json_encode($statusShipping) }}" data-pk="{{ $order->id }}" data-value="{{ $order->shipping_status }}" data-url="{{ gp247_route_admin('admin_order.post_update') }}" data-title="{{ gp247_language_render('order.shipping_status') }}">{{ $statusShipping[$order->shipping_status] ?? $order->shipping_status }}</a>
                            </p>

                            <p><strong>{{ gp247_language_render('order.payment_status') }}:</strong> 
                                <a href="#" class="updateStatus" data-name="payment_status" data-type="select" data-source="{{ json_encode($statusPayment) }}" data-pk="{{ $order->id }}" data-value="{{ $order->payment_status }}" data-url="{{ gp247_route_admin('admin_order.post_update') }}" data-title="{{ gp247_language_render('order.payment_status') }}">{{ $statusPayment[$order->payment_status] ?? $order->payment_status }}</a>
                            </p>

                            <p><strong>{{ gp247_language_render('order.shipping_method') }}:</strong> 
                                <a href="#" class="updateStatus" data-name="shipping_method" data-type="select" data-source="{{ json_encode($shippingMethod) }}" data-pk="{{ $order->id }}" data-value="{{ $order->shipping_method }}" data-url="{{ gp247_route_admin('admin_order.post_update') }}" data-title="{{ gp247_language_render('order.shipping_method') }}">{{ $order->shipping_method }}</a>
                            </p>

                            <p><strong>{{ gp247_language_render('order.payment_method') }}:</strong> 
                                <a href="#" class="updateStatus" data-name="payment_method" data-type="select" data-source="{{ json_encode($paymentMethod) }}" data-pk="{{ $order->id }}" data-value="{{ $order->payment_method }}" data-url="{{ gp247_route_admin('admin_order.post_update') }}" data-title="{{ gp247_language_render('order.payment_method') }}">{{ $order->payment_method }}</a>
                            </p>

                            <p><strong>{{ gp247_language_render('order.domain') }}:</strong> {{ $order->domain }}</p>
                            <p><strong>{{ gp247_language_render('admin.created_at') }}:</strong> {{ $order->created_at }}</p>
                            <p><strong>{{ gp247_language_render('order.currency') }}:</strong> {{ $order->currency }}</p>
                            <p><strong>{{ gp247_language_render('order.exchange_rate') }}:</strong> {{ $order->exchange_rate ?? 1 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Products Card --}}
            <div class="card mb-3">
                <div class="card-header font-weight-bold d-flex justify-content-between align-items-center">
                    <span>{{ gp247_language_render('product.name') }}</span>
                    <button type="button" class="btn btn-sm btn-success" id="add-item-button">
                        <i class="fa fa-plus"></i> {{ gp247_language_render('action.add') }}
                    </button>
                </div>
                <div class="card-body p-0">
                    <form id="form-add-item" action="" method="">
                        @csrf
                        <input type="hidden" name="order_id" value="{{ $order->id }}">
                        <table class="table table-bordered mb-0" id="items-table">
                            <thead class="thead-light">
                                <tr>
                                    <th width="35%">{{ gp247_language_render('product.name') }}</th>
                                    <th width="12%">{{ gp247_language_render('product.sku') }}</th>
                                    <th width="10%" class="text-right">{{ gp247_language_render('product.price') }}</th>
                                    <th width="8%" class="text-center">{{ gp247_language_render('product.quantity') }}</th>
                                    <th width="12%" class="text-right">{{ gp247_language_render('product.total_price') }}</th>
                                    <th width="8%" class="text-center">{{ gp247_language_render('product.tax') }}</th>
                                    <th width="5%">{{ gp247_language_render('action.title') }}</th>
                                </tr>
                            </thead>
                            <tbody id="items-body">
                                @foreach ($order->details as $item)
                                <tr data-item-id="{{ $item->id }}">
                                    <td>
                                        <strong>{{ $item->name }}</strong>
                                        @php
                                        $html = '';
                                        if($item->attribute && is_array(json_decode($item->attribute, true))) {
                                            $array = json_decode($item->attribute, true);
                                            foreach ($array as $key => $element) {
                                                $html .= '<br><small class="text-muted"><b>'.($attributesGroup[$key] ?? $key).'</b>: <i>'.gp247_render_option_price($element, $order->currency, $order->exchange_rate).'</i></small>';
                                            }
                                        }
                                        @endphp
                                        {!! $html !!}
                                    </td>
                                    <td>{{ $item->sku }}</td>
                                    <td class="text-right">
                                        <a href="#" class="edit-item-detail" data-value="{{ $item->price }}" data-name="price" data-type="text" min="0" data-pk="{{ $item->id }}" data-url="{{ gp247_route_admin('admin_order.post_edit_item') }}" data-title="{{ gp247_language_render('product.price') }}">{{ number_format($item->price, 0, ',', '.') }}</a>
                                    </td>
                                    <td class="text-center">
                                        <a href="#" class="edit-item-detail" data-value="{{ $item->qty }}" data-name="qty" data-type="number" min="0" data-pk="{{ $item->id }}" data-url="{{ gp247_route_admin('admin_order.post_edit_item') }}" data-title="{{ gp247_language_render('order.qty') }}">{{ $item->qty }}</a>
                                    </td>
                                    <td class="text-right item_id_{{ $item->id }}">{{ gp247_currency_render_symbol($item->total_price, $order->currency) }}</td>
                                    <td class="text-center">
                                        <a href="#" class="edit-item-detail" data-value="{{ $item->tax }}" data-name="tax" data-type="text" min="0" data-pk="{{ $item->id }}" data-url="{{ gp247_route_admin('admin_order.post_edit_item') }}" data-title="{{ gp247_language_render('order.tax') }}">{{ $item->tax }}%</a>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" onclick="deleteItem({{ $item->id }});" class="btn btn-sm btn-danger" title="{{ gp247_language_render('action.delete') }}">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </form>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-sm btn-warning" id="add-item-button-save" style="display: none;">
                        <i class="fa fa-save"></i> {{ gp247_language_render('action.save') }}
                    </button>
                </div>
            </div>

            {{-- Order Note Card --}}
            <div class="card mb-3">
                <div class="card-header font-weight-bold">{{ gp247_language_render('order.order_note') }}</div>
                <div class="card-body">
                    <p>
                        <a href="#" class="updateInfo" data-name="comment" data-type="textarea" data-pk="{{ $order->id }}" data-url="{{ gp247_route_admin('admin_order.post_update') }}" data-title="{{ gp247_language_render('order.order_note') }}">
                            {{ $order->comment ?: gp247_language_render('admin.no_data') }}
                        </a>
                    </p>
                </div>
            </div>

        </div>

        {{-- Right Column --}}
        <div class="col-md-4">
            
            {{-- Summary Box --}}
            <div class="card mb-3">
                <div class="card-header font-weight-bold">{{ gp247_language_render('order.totals.total') }}</div>
                <div class="card-body">
                    @foreach ($dataTotal as $element)
                        @if ($element['code'] == 'subtotal')
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ $element['title'] }}:</span>
                            <strong class="data-{{ $element['code'] }}">{{ gp247_currency_format($element['value']) }}</strong>
                        </div>
                        @endif
                        
                        @if ($element['code'] == 'tax')
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ $element['title'] }}:</span>
                            <strong class="data-{{ $element['code'] }}">{{ gp247_currency_format($element['value']) }}</strong>
                        </div>
                        @endif

                        @if ($element['code'] == 'shipping')
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ $element['title'] }}:</span>
                            <strong>
                                <a href="#" class="updatePrice data-{{ $element['code'] }}" data-name="{{ $element['code'] }}" data-type="text" data-pk="{{ $element['id'] }}" data-url="{{ gp247_route_admin('admin_order.post_update') }}" data-title="{{ gp247_language_render('order.totals.shipping') }}">{{ $element['value'] }}</a>
                            </strong>
                        </div>
                        @endif

                        @if ($element['code'] == 'discount')
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ $element['title'] }} (-):</span>
                            <strong>
                                <a href="#" class="updatePrice data-{{ $element['code'] }}" data-name="{{ $element['code'] }}" data-type="text" data-pk="{{ $element['id'] }}" data-url="{{ gp247_route_admin('admin_order.post_update') }}" data-title="{{ gp247_language_render('order.totals.discount') }}">{{ $element['value'] }}</a>
                            </strong>
                        </div>
                        @endif

                        @if ($element['code'] == 'other_fee')
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ $element['title'] }}:</span>
                            <strong>
                                <a href="#" class="updatePrice data-{{ $element['code'] }}" data-name="{{ $element['code'] }}" data-type="text" data-pk="{{ $element['id'] }}" data-url="{{ gp247_route_admin('admin_order.post_update') }}" data-title="{{ config('cart.process.other_fee.title') }}">{{ $element['value'] }}</a>
                            </strong>
                        </div>
                        @endif

                        @if ($element['code'] == 'total')
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="font-weight-bold">{{ $element['title'] }}:</span>
                            <strong class="text-primary data-{{ $element['code'] }}">{{ gp247_currency_format($element['value']) }}</strong>
                        </div>
                        @endif

                        @if ($element['code'] == 'received')
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ $element['title'] }} (-):</span>
                            <strong>
                                <a href="#" class="updatePrice data-{{ $element['code'] }}" data-name="{{ $element['code'] }}" data-type="text" data-pk="{{ $element['id'] }}" data-url="{{ gp247_route_admin('admin_order.post_update') }}" data-title="{{ gp247_language_render('order.totals.received') }}">{{ $element['value'] }}</a>
                            </strong>
                        </div>
                        @endif
                    @endforeach

                    @php
                    if($order->balance == 0) {
                        $balanceClass = 'text-success';
                    } elseif($order->balance < 0) {
                        $balanceClass = 'text-danger';
                    } else {
                        $balanceClass = 'font-weight-bold';
                    }
                    @endphp
                    <hr>
                    <div class="d-flex justify-content-between data-balance">
                        <span class="font-weight-bold">{{ gp247_language_render('order.totals.balance') }}:</span>
                        <strong class="{{ $balanceClass }}">{{ ($order->balance === NULL) ? gp247_currency_format($order->total) : gp247_currency_format($order->balance) }}</strong>
                    </div>
                </div>
            </div>

            {{-- History Card --}}
            <div class="card">
                <div class="card-header font-weight-bold d-flex justify-content-between align-items-center">
                    <span>{{ gp247_language_render('admin.order.order_history') }}</span>
                </div>
                <div class="card-body p-2" style="max-height:600px; overflow-y:auto">
                    @if (count($order->history))
                        @foreach ($order->history->sortKeysDesc()->all() as $history)
                        <div class="border-left border-primary pl-3 mb-3">
                            <small class="text-muted d-block">{{ $history['add_date'] }}</small>
                            <small class="text-info d-block">{{ \GP247\Core\Models\AdminUser::find($history['admin_id'])->name ?? gp247_language_render('admin.system') }}</small>
                            <p class="mb-0 small">{!! $history['content'] !!}</p>
                        </div>
                        @endforeach
                    @else
                    <p class="text-muted small text-center">{{ gp247_language_render('admin.no_data') }}</p>
                    @endif
                    
                    <hr>
                    <div class="border-left border-secondary pl-3">
                        <small class="text-muted d-block"><b>Agent:</b> {{ $order->user_agent }}</small>
                        <small class="text-muted d-block"><b>IP:</b> {{ $order->ip }}</small>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

@php
$htmlSelectProduct = '<tr>
            <td>
              <select onChange="selectProduct($(this));" class="add_id form-control select2" name="add_id[]" style="width:100% !important;">
              <option value="0">'.gp247_language_render('admin.order.select_product').'</option>';
              if(count($products)){
                foreach ($products as $pId => $product){
                  $htmlSelectProduct .='<option value="'.$pId.'" >'.$product['name'].'('.$product['sku'].')</option>';
                 }
              }
$htmlSelectProduct .='
            </select>
            <span class="add_attr"></span>
          </td>
            <td><input type="text" disabled class="add_sku form-control" value=""></td>
            <td><input onChange="update_total($(this));" type="number" step="0.01" min="0" class="add_price form-control" name="add_price[]" value="0"></td>
            <td><input onChange="update_total($(this));" type="number" min="0" class="add_qty form-control" name="add_qty[]" value="0"></td>
            <td><input type="number" disabled class="add_total form-control" value="0"></td>
            <td><input type="number" step="0.01" min="0" class="add_tax form-control" name="add_tax[]" value="0"></td>
            <td><button onClick="$(this).parent().parent().remove();" class="btn btn-danger btn-sm" data-title="Delete"><i class="fa fa-times"></i></button></td>
          </tr>';
$htmlSelectProduct = str_replace("\n", '', $htmlSelectProduct);
$htmlSelectProduct = str_replace("\t", '', $htmlSelectProduct);
$htmlSelectProduct = str_replace("\r", '', $htmlSelectProduct);
$htmlSelectProduct = str_replace("'", '"', $htmlSelectProduct);
@endphp
@endsection

@push('styles')
<style>
.card {
    box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
}
.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}
.table thead.thead-light th {
    background-color: #f8f9fa;
    font-weight: 600;
}
.badge-new { background-color: #17a2b8; }
.badge-processing { background-color: #ffc107; }
.badge-completed { background-color: #28a745; }
.badge-cancelled { background-color: #dc3545; }
.border-left {
    border-left: 3px solid #007bff !important;
}
</style>
<link rel="stylesheet" href="{{ gp247_file('GP247/Core/plugin/bootstrap-editable.css') }}">
@endpush

@push('scripts')
<script src="{{ gp247_file('GP247/Core/plugin/jquery.pjax.js') }}"></script>
<script src="{{ gp247_file('GP247/Core/plugin/bootstrap-editable.min.js') }}"></script>

<script>
function update_total(e) {
    node = e.closest('tr');
    var qty = node.find('.add_qty').eq(0).val();
    var price = node.find('.add_price').eq(0).val();
    node.find('.add_total').eq(0).val(qty * price);
}

function selectProduct(element) {
    node = element.closest('tr');
    var id = node.find('option:selected').eq(0).val();
    if(!id) {
        node.find('.add_sku').val('');
        node.find('.add_qty').eq(0).val('');
        node.find('.add_price').eq(0).val('');
        node.find('.add_attr').html('');
        node.find('.add_tax').html('');
    } else {
        $.ajax({
            url: '{{ gp247_route_admin('admin_order.product_info') }}',
            type: "get",
            dateType: "application/json; charset=utf-8",
            data: {
                id: id,
                order_id: '{{ $order->id }}',
            },
            beforeSend: function() {
                $('#loading').show();
            },
            success: function(returnedData) {
                node.find('.add_sku').val(returnedData.sku);
                node.find('.add_qty').eq(0).val(1);
                node.find('.add_price').eq(0).val(returnedData.price_final * {!! ($order->exchange_rate) ?? 1 !!});
                node.find('.add_total').eq(0).val(returnedData.price_final * {!! ($order->exchange_rate) ?? 1 !!});
                node.find('.add_attr').eq(0).html(returnedData.renderAttDetails);
                node.find('.add_tax').eq(0).html(returnedData.tax);
                $('#loading').hide();
            }
        });
    }
}

$('#add-item-button').click(function() {
    var html = '{!! $htmlSelectProduct !!}';
    $('#items-body').append(html);
    $('.select2').select2();
    $('#add-item-button-save').show();
});

$('#add-item-button-save').click(function(event) {
    $('#add-item-button').prop('disabled', true);
    $('#add-item-button-save').button('loading');
    $.ajax({
        url: '{{ gp247_route_admin("admin_order.post_add_item") }}',
        type: 'post',
        dataType: 'json',
        data: $('form#form-add-item').serialize(),
        beforeSend: function() {
            $('#loading').show();
        },
        success: function(result) {
            $('#loading').hide();
            if(parseInt(result.error) == 0) {
                location.reload();
            } else {
                alertJs('error', result.msg);
            }
        }
    });
});

$(document).ready(function() {
    all_editable();
});

function all_editable() {
    $.fn.editable.defaults.params = function(params) {
        params._token = "{{ csrf_token() }}";
        return params;
    };

    $('.updateInfo').editable({
        success: function(response) {
            if(response.error == 0) {
                alertJs('success', response.msg);
            } else {
                alertJs('error', response.msg);
            }
        }
    });

    $(".updatePrice").on("shown", function(e, editable) {
        var value = $(this).text().replace(/,/g, "");
        editable.input.$input.val(parseInt(value));
    });

    $('.updateStatus').editable({
        validate: function(value) {
            if (value == '') {
                return '{{ gp247_language_render('admin.not_empty') }}';
            }
        },
        success: function(response) {
            if(response.error == 0) {
                alertJs('success', response.msg);
            } else {
                alertJs('error', response.msg);
            }
        }
    });

    $('.updateInfoRequired').editable({
        validate: function(value) {
            if (value == '') {
                return '{{ gp247_language_render('admin.not_empty') }}';
            }
        },
        success: function(response, newValue) {
            if(response.error == 0) {
                alertJs('success', response.msg);
            } else {
                alertJs('error', response.msg);
            }
        }
    });

    $('.edit-item-detail').editable({
        ajaxOptions: {
            type: 'post',
            dataType: 'json'
        },
        validate: function(value) {
            if (value == '') {
                return '{{ gp247_language_render('admin.not_empty') }}';
            }
            if (!$.isNumeric(value)) {
                return '{{ gp247_language_render('admin.only_numeric') }}';
            }
        },
        success: function(response, newValue) {
            if(response.error == 0) {
                $('.data-shipping').html(response.detail.shipping);
                $('.data-received').html(response.detail.received);
                $('.data-subtotal').html(response.detail.subtotal);
                $('.data-tax').html(response.detail.tax);
                $('.data-total').html(response.detail.total);
                $('.data-discount').html(response.detail.discount);
                $('.item_id_' + response.detail.item_id).html(response.detail.item_total_price);
                var objbalance = $('.data-balance').eq(0);
                objbalance.before(response.detail.balance);
                objbalance.remove();
                alertJs('success', response.msg);
            } else {
                alertJs('error', response.msg);
            }
        }
    });

    $('.updatePrice').editable({
        ajaxOptions: {
            type: 'post',
            dataType: 'json'
        },
        validate: function(value) {
            if (value == '') {
                return '{{ gp247_language_render('admin.not_empty') }}';
            }
            if (!$.isNumeric(value)) {
                return '{{ gp247_language_render('admin.only_numeric') }}';
            }
        },
        success: function(response, newValue) {
            if(response.error == 0) {
                $('.data-shipping').html(response.detail.shipping);
                $('.data-received').html(response.detail.received);
                $('.data-subtotal').html(response.detail.subtotal);
                $('.data-tax').html(response.detail.tax);
                $('.data-total').html(response.detail.total);
                $('.data-discount').html(response.detail.discount);
                var objbalance = $('.data-balance').eq(0);
                objbalance.before(response.detail.balance);
                objbalance.remove();
                alertJs('success', response.msg);
            } else {
                alertJs('error', response.msg);
            }
        }
    });
}

function deleteItem(id) {
    Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: true,
    }).fire({
        title: '{{ gp247_language_render('action.delete_confirm') }}',
        text: "",
        type: 'warning',
        showCancelButton: true,
        confirmButtonText: '{{ gp247_language_render('action.confirm_yes') }}',
        confirmButtonColor: "#DD6B55",
        cancelButtonText: '{{ gp247_language_render('action.confirm_no') }}',
        reverseButtons: true,
        preConfirm: function() {
            return new Promise(function(resolve) {
                $.ajax({
                    method: 'POST',
                    url: '{{ gp247_route_admin("admin_order.post_delete_item") }}',
                    data: {
                        'pId': id,
                        _token: '{{ csrf_token() }}',
                    },
                    success: function(response) {
                        if(response.error == 0) {
                            location.reload();
                            alertJs('success', response.msg);
                        } else {
                            alertJs('error', response.msg);
                        }
                    }
                });
            });
        }
    }).then((result) => {
        if (result.value) {
            alertMsg('success', '{{ gp247_language_render('action.delete_confirm_deleted_msg') }}', '{{ gp247_language_render('action.delete_confirm_deleted') }}');
        }
    });
}

$(document).ready(function() {
    if ($.support.pjax) {
        $.pjax.defaults.timeout = 2000;
    }
});
</script>
@endpush
