@extends('gp247-core::layout')
@php $title = gp247_language_render('admin.order.add_new_title'); @endphp

@section('main')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col">
            <h4>{{ gp247_language_render('admin.order.add_new_title') }}</h4>
        </div>
        <div class="col text-right">
            <a href="{{ gp247_route_admin('admin_order.index') }}" class="btn btn-secondary btn-sm">
                <i class="fa fa-arrow-left"></i> {{ gp247_language_render('admin.back_list') }}
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ gp247_route_admin('admin_order.post_create') }}" method="post" id="order-form">
        @csrf
        
        <div class="row">
            {{-- Left Column --}}
            <div class="col-md-8">
                
                {{-- Customer Information Card --}}
                <div class="card mb-3">
                    <div class="card-header font-weight-bold">{{ gp247_language_render('admin.order.select_customer') }}</div>
                    <div class="card-body">
                        
                        <div class="form-group row {{ $errors->has('customer_id') ? 'text-red' : '' }}">
                            <label for="customer_id" class="col-sm-3 col-form-label">
                                {{ gp247_language_render('admin.order.select_customer') }} <span class="text-danger">*</span>
                            </label>
                            <div class="col-sm-8">
                                <select class="form-control customer_id select2" style="width: 100%;" name="customer_id">
                                    <option value="">{{ gp247_language_render('admin.order.select_customer') }}</option>
                                    @foreach ($users as $k => $v)
                                        <option value="{{ $k }}" {{ (old('customer_id') == $k) ? 'selected' : '' }}>{{ $v->name.' <'.$v->email.'>' }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('customer_id'))
                                    <span class="text-sm text-danger">{{ $errors->first('customer_id') }}</span>
                                @endif
                            </div>
                            <div class="col-sm-1">
                                <a href="{{ gp247_route_admin('admin_customer.index') }}" target="_blank">
                                    <button type="button" class="btn btn-success btn-sm" title="{{ gp247_language_render('admin.add_new') }}">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </a>
                            </div>
                        </div>

                        <div class="form-group row {{ $errors->has('email') ? 'text-red' : '' }}">
                            <label for="email" class="col-sm-3 col-form-label">{{ gp247_language_render('order.email') }}</label>
                            <div class="col-sm-9">
                                <input type="email" name="email" value="{{ old('email') }}" class="form-control email" required>
                                @if ($errors->has('email'))
                                    <span class="text-sm text-danger">{{ $errors->first('email') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row {{ $errors->has('first_name') ? 'text-red' : '' }}">
                            <label for="first_name" class="col-sm-3 col-form-label">{{ gp247_language_render('order.first_name') }}</label>
                            <div class="col-sm-9">
                                <input type="text" name="first_name" value="{{ old('first_name') }}" class="form-control first_name">
                                @if ($errors->has('first_name'))
                                    <span class="text-sm text-danger">{{ $errors->first('first_name') }}</span>
                                @endif
                            </div>
                        </div>

                        @if (gp247_config_admin('customer_lastname'))
                        <div class="form-group row {{ $errors->has('last_name') ? 'text-red' : '' }}">
                            <label for="last_name" class="col-sm-3 col-form-label">{{ gp247_language_render('order.last_name') }}</label>
                            <div class="col-sm-9">
                                <input type="text" name="last_name" value="{{ old('last_name') }}" class="form-control last_name">
                                @if ($errors->has('last_name'))
                                    <span class="text-sm text-danger">{{ $errors->first('last_name') }}</span>
                                @endif
                            </div>
                        </div>
                        @endif

                        @if (gp247_config_admin('customer_phone'))
                        <div class="form-group row {{ $errors->has('phone') ? 'text-red' : '' }}">
                            <label for="phone" class="col-sm-3 col-form-label">{{ gp247_language_render('order.phone') }}</label>
                            <div class="col-sm-9">
                                <input type="text" name="phone" value="{{ old('phone') }}" class="form-control phone">
                                @if ($errors->has('phone'))
                                    <span class="text-sm text-danger">{{ $errors->first('phone') }}</span>
                                @endif
                            </div>
                        </div>
                        @endif

                        @if (gp247_config_admin('customer_company'))
                        <div class="form-group row {{ $errors->has('company') ? 'text-red' : '' }}">
                            <label for="company" class="col-sm-3 col-form-label">{{ gp247_language_render('order.company') }}</label>
                            <div class="col-sm-9">
                                <input type="text" name="company" value="{{ old('company') }}" class="form-control company">
                                @if ($errors->has('company'))
                                    <span class="text-sm text-danger">{{ $errors->first('company') }}</span>
                                @endif
                            </div>
                        </div>
                        @endif

                        @if (gp247_config_admin('customer_address1'))
                        <div class="form-group row {{ $errors->has('address1') ? 'text-red' : '' }}">
                            <label for="address1" class="col-sm-3 col-form-label">{{ gp247_language_render('order.address1') }} @if(gp247_config_admin('customer_address1_required'))<span class="text-danger">*</span>@endif</label>
                            <div class="col-sm-9">
                                <input type="text" name="address1" value="{{ old('address1') }}" class="form-control address1">
                                @if ($errors->has('address1'))
                                    <span class="text-sm text-danger">{{ $errors->first('address1') }}</span>
                                @endif
                            </div>
                        </div>
                        @endif

                        @if (gp247_config_admin('customer_country'))
                        <div class="form-group row {{ $errors->has('country') ? 'text-red' : '' }}">
                            <label for="country" class="col-sm-3 col-form-label">{{ gp247_language_render('order.country') }} @if(gp247_config_admin('customer_country_required'))<span class="text-danger">*</span>@endif</label>
                            <div class="col-sm-9">
                                <select class="form-control country select2" style="width: 100%;" name="country">
                                    <option value=""></option>
                                    @foreach ($countries as $k => $v)
                                        <option value="{{ $k }}" {{ (old('country') == $k) ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('country'))
                                    <span class="text-sm text-danger">{{ $errors->first('country') }}</span>
                                @endif
                            </div>
                        </div>
                        @endif

                    </div>
                </div>

                {{-- Products Card --}}
                <div class="card mb-3">
                    <div class="card-header font-weight-bold d-flex justify-content-between align-items-center">
                        <span>{{ gp247_language_render('product.name') }}</span>
                        <button type="button" class="btn btn-sm btn-success" id="btn-add-product">
                            <i class="fa fa-plus"></i> {{ gp247_language_render('action.add') }}
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-bordered mb-0" id="products-table">
                            <thead class="thead-light">
                                <tr>
                                    <th width="40%">{{ gp247_language_render('product.name') }}</th>
                                    <th width="15%">{{ gp247_language_render('product.sku') }}</th>
                                    <th width="10%" class="text-center">{{ gp247_language_render('product.quantity') }}</th>
                                    <th width="12%" class="text-right">{{ gp247_language_render('product.price') }}</th>
                                    <th width="8%" class="text-center">{{ gp247_language_render('product.tax') }}</th>
                                    <th width="10%" class="text-right">{{ gp247_language_render('product.total_price') }}</th>
                                    <th width="5%"></th>
                                </tr>
                            </thead>
                            <tbody id="products-body">
                                {{-- Products will be added here dynamically --}}
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Payment & Shipping Information Card --}}
                <div class="card mb-3">
                    <div class="card-header font-weight-bold">{{ gp247_language_render('order.payment_method') }} & {{ gp247_language_render('order.shipping_method') }}</div>
                    <div class="card-body">
                        
                        <div class="form-group row {{ $errors->has('currency') ? 'text-red' : '' }}">
                            <label for="currency" class="col-sm-3 col-form-label">{{ gp247_language_render('order.currency') }} <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select class="form-control currency select2" style="width: 100%;" name="currency" required>
                                    <option value=""></option>
                                    @foreach ($currencies as $v)
                                        <option value="{{ $v->code }}" {{ (old('currency') == $v->code) ? 'selected' : '' }}>{{ $v->name }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('currency'))
                                    <span class="text-sm text-danger">{{ $errors->first('currency') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row {{ $errors->has('exchange_rate') ? 'text-red' : '' }}">
                            <label for="exchange_rate" class="col-sm-3 col-form-label">{{ gp247_language_render('order.exchange_rate') }}</label>
                            <div class="col-sm-9">
                                <input type="text" name="exchange_rate" value="{{ old('exchange_rate', 1) }}" class="form-control exchange_rate">
                                @if ($errors->has('exchange_rate'))
                                    <span class="text-sm text-danger">{{ $errors->first('exchange_rate') }}</span>
                                @endif
                            </div>
                        </div>

                        @if (gp247_config('use_payment'))
                        <div class="form-group row {{ $errors->has('payment_method') ? 'text-red' : '' }}">
                            <label for="payment_method" class="col-sm-3 col-form-label">{{ gp247_language_render('order.payment_method') }}</label>
                            <div class="col-sm-9">
                                <select class="form-control payment_method select2" style="width: 100%;" name="payment_method">
                                    @foreach ($paymentMethod as $k => $v)
                                        <option value="{{ $k }}" {{ (old('payment_method') == $k) ? 'selected' : '' }}>{{ gp247_language_render($v) }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('payment_method'))
                                    <span class="text-sm text-danger">{{ $errors->first('payment_method') }}</span>
                                @endif
                            </div>
                        </div>
                        @endif

                        @if (gp247_config('use_shipping'))
                        <div class="form-group row {{ $errors->has('shipping_method') ? 'text-red' : '' }}">
                            <label for="shipping_method" class="col-sm-3 col-form-label">{{ gp247_language_render('order.shipping_method') }}</label>
                            <div class="col-sm-9">
                                <select class="form-control shipping_method select2" style="width: 100%;" name="shipping_method">
                                    @foreach ($shippingMethod as $k => $v)
                                        <option value="{{ $k }}" {{ (old('shipping_method') == $k) ? 'selected' : '' }}>{{ gp247_language_render($v) }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('shipping_method'))
                                    <span class="text-sm text-danger">{{ $errors->first('shipping_method') }}</span>
                                @endif
                            </div>
                        </div>
                        @endif

                        <div class="form-group row {{ $errors->has('status') ? 'text-red' : '' }}">
                            <label for="status" class="col-sm-3 col-form-label">{{ gp247_language_render('order.status') }}</label>
                            <div class="col-sm-9">
                                <select class="form-control status select2" style="width: 100%;" name="status">
                                    @foreach ($orderStatus as $k => $v)
                                        <option value="{{ $k }}" {{ (old('status') == $k) ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('status'))
                                    <span class="text-sm text-danger">{{ $errors->first('status') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="comment" class="col-sm-3 col-form-label">{{ gp247_language_render('order.note') }}</label>
                            <div class="col-sm-9">
                                <textarea name="comment" class="form-control comment" rows="3">{{ old('comment') }}</textarea>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            {{-- Right Column --}}
            <div class="col-md-4">
                
                {{-- Summary Box --}}
                <div class="card">
                    <div class="card-header font-weight-bold">{{ gp247_language_render('order.totals.total') }}</div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ gp247_language_render('order.totals.subtotal') }}:</span>
                            <strong id="total-subtotal">0</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ gp247_language_render('order.totals.tax') }}:</span>
                            <strong id="total-tax">0</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ gp247_language_render('order.totals.shipping') }}:</span>
                            <input type="number" name="shipping" id="shipping-input" class="form-control form-control-sm" value="0" step="0.01" style="width:100px; display:inline-block;">
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>{{ gp247_language_render('order.totals.discount') }} (-):</span>
                            <input type="number" name="discount" id="discount-input" class="form-control form-control-sm" value="0" step="0.01" style="width:100px; display:inline-block;">
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="font-weight-bold">{{ gp247_language_render('order.totals.total') }}:</span>
                            <strong id="grand-total" class="text-primary">0</strong>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fa fa-save"></i> {{ gp247_language_render('action.submit') }}
                        </button>
                        <button type="reset" class="btn btn-warning btn-block">
                            <i class="fa fa-undo"></i> {{ gp247_language_render('action.reset') }}
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>

@php
$productsArray = [];
if(isset($products)) {
    foreach($products as $id => $product) {
        $productsArray[$id] = [
            'id' => $id,
            'name' => $product['name'] ?? '',
            'sku' => $product['sku'] ?? '',
            'price' => $product['price'] ?? 0
        ];
    }
}
@endphp
<script type="application/json" id="products-data">@json($productsArray)</script>
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
</style>
@endpush

@push('scripts')
<script>
const PRODUCTS = JSON.parse(document.getElementById('products-data')?.textContent || '{}');
console.log('PRODUCTS loaded:', PRODUCTS);
let productIndex = 0;

function formatNumber(n) {
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(n);
}

function recalculate() {
    let subtotal = 0;
    let taxTotal = 0;
    
    $('#products-body tr').each(function() {
        const qty = parseFloat($(this).find('[name*="[qty]"]').val()) || 0;
        const price = parseFloat($(this).find('[name*="[price]"]').val()) || 0;
        const taxRate = parseFloat($(this).find('[name*="[tax]"]').val()) || 0;
        
        const itemSubtotal = qty * price;
        const itemTax = itemSubtotal * taxRate / 100;
        
        $(this).find('.item-total').text(formatNumber(itemSubtotal + itemTax));
        
        subtotal += itemSubtotal;
        taxTotal += itemTax;
    });
    
    const shipping = parseFloat($('#shipping-input').val()) || 0;
    const discount = parseFloat($('#discount-input').val()) || 0;
    const grandTotal = subtotal + taxTotal + shipping - discount;
    
    $('#total-subtotal').text(formatNumber(subtotal));
    $('#total-tax').text(formatNumber(taxTotal));
    $('#grand-total').text(formatNumber(grandTotal));
}

function buildProductOptions() {
    let opts = '<option value="">{{ gp247_language_render('admin.order.select_product') }}</option>';
    $.each(PRODUCTS, function(id, product) {
        const name = product.name || '';
        const sku = product.sku || '';
        const price = product.price || 0;
        opts += `<option value="${id}" data-sku="${sku}" data-price="${price}">${name} (${sku})</option>`;
    });
    return opts;
}

function addProductRow() {
    const idx = productIndex++;
    const row = `
    <tr>
        <td>
            <select name="products[${idx}][product_id]" class="form-control form-control-sm product-select" required>
                ${buildProductOptions()}
            </select>
            <input type="hidden" name="products[${idx}][name]" class="product-name">
        </td>
        <td class="product-sku text-muted small align-middle">-</td>
        <td><input type="number" name="products[${idx}][qty]" class="form-control form-control-sm product-qty" value="1" min="0.01" step="0.01" required></td>
        <td><input type="number" name="products[${idx}][price]" class="form-control form-control-sm product-price" value="0" min="0" step="0.01" required></td>
        <td><div class="input-group input-group-sm" style="width:85px"><input type="number" name="products[${idx}][tax]" class="form-control form-control-sm product-tax" value="0" min="0" max="100" step="0.1"><div class="input-group-append"><span class="input-group-text">%</span></div></div></td>
        <td class="item-total text-right align-middle">0</td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger remove-row"><i class="fa fa-times"></i></button></td>
    </tr>`;
    
    const $row = $(row);
    $('#products-body').append($row);
    
    const $select = $row.find('.product-select');
    $select.select2({
        width: '100%',
        dropdownParent: $('body'),
    });
    
    $select.on('change', function() {
        const $opt = $(this).find('option:selected');
        const $tr = $(this).closest('tr');
        const sku = $opt.data('sku') || '';
        const price = $opt.data('price') || 0;
        const name = $opt.text();
        
        $tr.find('.product-sku').text(sku);
        $tr.find('.product-name').val(name);
        $tr.find('.product-price').val(price);
        recalculate();
    });
    
    recalculate();
}

$('#btn-add-product').click(addProductRow);

$(document).on('click', '.remove-row', function() {
    $(this).closest('tr').remove();
    recalculate();
});

$(document).on('input', '.product-qty, .product-price, .product-tax, #shipping-input, #discount-input', recalculate);

$('[name="customer_id"]').change(function() {
    const id = $(this).val();
    if(id) {
        $.ajax({
            url: '{{ gp247_route_admin('admin_order.user_info') }}',
            type: "get",
            dateType: "application/json; charset=utf-8",
            data: { id: id },
            beforeSend: function() {
                $('#loading').show();
            },
            success: function(result) {
                const returnedData = JSON.parse(result);
                $('[name="email"]').val(returnedData.email);
                $('[name="first_name"]').val(returnedData.first_name);
                $('[name="last_name"]').val(returnedData.last_name);
                $('[name="first_name_kana"]').val(returnedData.first_name_kana);
                $('[name="last_name_kana"]').val(returnedData.last_name_kana);
                $('[name="address1"]').val(returnedData.address1);
                $('[name="address2"]').val(returnedData.address2);
                $('[name="address3"]').val(returnedData.address3);
                $('[name="phone"]').val(returnedData.phone);
                $('[name="company"]').val(returnedData.company);
                $('[name="postcode"]').val(returnedData.postcode);
                $('[name="country"]').val(returnedData.country).change();
                $('#loading').hide();
            }
        });
    } else {
        // Reset form if no customer selected
        $('[name="email"]').val('');
        $('[name="first_name"]').val('');
        $('[name="last_name"]').val('');
    }
});

$('[name="currency"]').change(function() {
    const currency = $(this).val();
    const jsonCurrency = {!! $currenciesRate !!};
    $('[name="exchange_rate"]').val(jsonCurrency[currency]);
});

$('#order-form').on('submit', function(e) {
    if ($('#products-body tr').length === 0) {
        e.preventDefault();
        alert('{{ gp247_language_render('admin.order.select_product') }}');
        return false;
    }
});

$(document).ready(function() {
    // Initialize Select2 Elements (same as original)
    $('.select2').select2();
    
    // Add one product row by default
    addProductRow();
});
</script>
@endpush
