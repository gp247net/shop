@extends('gp247-core::layout')

@section('main')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header with-border">
                <h2 class="card-title">{{ $title_description??'' }}</h2>
                <div class="card-tools">
                    <div class="btn-group float right  mr-5">
                        <a href="{{ gp247_route_admin('admin_product.index') }}" class="btn  btn-flat btn-default" title="List">
                            <i class="fa fa-list"></i><span class="hidden-xs"> {{ gp247_language_render('admin.back_list') }}</span>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /.card-header -->

            <!-- form start -->
            <form action="{{ gp247_route_admin('admin_product.edit',['id'=>$product['id']]) }}" method="post" accept-charset="UTF-8"
                class="form-horizontal" id="form-main" enctype="multipart/form-data">

                @if (gp247_config_admin('product_kind'))
                <div id="start-add" class="d-flex d-flex justify-content-center mb-3 kind-{{ $product->kind }}">
                    <div style="width: 300px;text-align: center; z-index:999">
                        <b>{{ gp247_language_render('product.kind') }}:</b> {{ $kinds[$product->kind]??'' }}
                    </div>
                </div>    
                @endif

                <div class="card-body">
                    {{-- Descriptions --}}
                    @php
                    $descriptions = $product->descriptions->keyBy('lang')->toArray();
                    @endphp

                    @foreach ($languages as $code => $language)
                    <div class="card">
                        <div class="card-header with-border">
                            <h3 class="card-title">{{ $language->name }} {!! gp247_image_render($language->icon,'20px','20px', $language->name) !!}</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                
                        <div class="card-body">

                            <div class="form-group row  {{ $errors->has('descriptions.'.$code.'.name') ? ' text-red' : '' }}">
                                <label for="{{ $code }}__name"
                                    class="col-sm-2 col-form-label">{{ gp247_language_render('product.name') }} <span class="seo" title="SEO"><i class="fa fa-coffee" aria-hidden="true"></i></span></label>

                                <div class="col-sm-8">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-pencil-alt"></i></span>
                                        </div>
                                        <input type="text" id="{{ $code }}__name" name="descriptions[{{ $code }}][name]"
                                            value="{{ old('descriptions.'.$code.'.name',($descriptions[$code]['name']??'')) }}"
                                            class="form-control {{ $code.'__name' }}" placeholder="" />
                                    </div>
                                    @if ($errors->has('descriptions.'.$code.'.name'))
                                    <span class="form-text">
                                        <i class="fa fa-info-circle"></i>
                                        {{ $errors->first('descriptions.'.$code.'.name') }}
                                    </span>
                                    @else
                                        <span class="form-text">
                                            <i class="fa fa-info-circle"></i> {{ gp247_language_render('admin.max_c',['max'=>200]) }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div
                                class="form-group row  {{ $errors->has('descriptions.'.$code.'.keyword') ? ' text-red' : '' }}">
                                <label for="{{ $code }}__keyword"
                                    class="col-sm-2 col-form-label">{{ gp247_language_render('product.keyword') }} <span class="seo" title="SEO"><i class="fa fa-coffee" aria-hidden="true"></i></span></label>
                                <div class="col-sm-8">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-pencil-alt"></i></span>
                                        </div>
                                        <input type="text" id="{{ $code }}__keyword"
                                            name="descriptions[{{ $code }}][keyword]"
                                            value="{{ old('descriptions.'.$code.'.keyword',($descriptions[$code]['keyword']??'')) }}"
                                            class="form-control {{ $code.'__keyword' }}" placeholder="" />
                                    </div>
                                    @if ($errors->has('descriptions.'.$code.'.keyword'))
                                    <span class="form-text">
                                        <i class="fa fa-info-circle"></i>
                                        {{ $errors->first('descriptions.'.$code.'.keyword') }}
                                    </span>
                                    @else
                                        <span class="form-text">
                                            <i class="fa fa-info-circle"></i> {{ gp247_language_render('admin.max_c',['max'=>200]) }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div
                                class="form-group row  {{ $errors->has('descriptions.'.$code.'.description') ? ' text-red' : '' }}">
                                <label for="{{ $code }}__description"
                                    class="col-sm-2 col-form-label">{{ gp247_language_render('product.description') }} <span class="seo" title="SEO"><i class="fa fa-coffee" aria-hidden="true"></i></span></label>
                                <div class="col-sm-8">
                                        <textarea  id="{{ $code }}__description"
                                            name="descriptions[{{ $code }}][description]"
                                            class="form-control {{ $code.'__description' }}" placeholder="" />{{ old('descriptions.'.$code.'.description',($descriptions[$code]['description']??'')) }}</textarea>
                                    @if ($errors->has('descriptions.'.$code.'.description'))
                                    <span class="form-text">
                                        <i class="fa fa-info-circle"></i>
                                        {{ $errors->first('descriptions.'.$code.'.description') }}
                                    </span>
                                    @else
                                        <span class="form-text">
                                            <i class="fa fa-info-circle"></i> {{ gp247_language_render('admin.max_c',['max'=>300]) }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                        @if ($product->kind == GP247_PRODUCT_SINGLE || $product->kind == GP247_PRODUCT_BUILD)
                            <div
                                class="form-group row {{ $errors->has('descriptions.'.$code.'.content') ? ' text-red' : '' }}">
                                <label for="{{ $code }}__content"
                                    class="col-sm-2 col-form-label">{{ gp247_language_render('product.content') }}</label>
                                <div class="col-sm-8">
                                    <textarea id="{{ $code }}__content" class="editor"
                                        name="descriptions[{{ $code }}][content]">
                                        {{ old('descriptions.'.$code.'.content',($descriptions[$code]['content']??'')) }}</textarea>
                                    @if ($errors->has('descriptions.'.$code.'.content'))
                                    <span class="form-text">
                                        <i class="fa fa-info-circle"></i>
                                        {{ $errors->first('descriptions.'.$code.'.content') }}
                                    </span>
                                    @endif
                                </div>
                            </div>
                        @endif
                        </div>
                    </div>
                    @endforeach
                    {{-- //Descriptions --}}

                        {{-- Category --}}
                        @php
                        $listCate = [];
                        $category = old('category', $product->categories->pluck('id')->toArray());
                        if(is_array($category)){
                            foreach($category as $value){
                                $listCate[] = $value;
                            }
                        }
                        @endphp

                        <div class="form-group row {{ $errors->has('category') ? ' text-red' : '' }}">
                            <label for="category"
                                class="col-sm-2 col-form-label">{{ gp247_language_render('admin.product.select_category') }}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                <select class="form-control category select2" multiple="multiple"
                                    data-placeholder="{{ gp247_language_render('admin.product.select_category') }}"
                                    name="category[]">
                                    <option value=""></option>
                                    @foreach ($categories as $k => $v)
                                    <option value="{{ $k }}"
                                        {{ (count($listCate) && in_array($k, $listCate))?'selected':'' }}>{{ $v }}
                                    </option>
                                    @endforeach
                                </select>
                                <div class="input-group-append">
                                    <a target=_new href="{{ gp247_route_admin('admin_category.index') }}" class="btn  btn-flat" title="New">
                                        <i class="fa fa-plus" title="{{ gp247_language_render('action.add') }}"></i>
                                     </a>
                                </div>
                                </div>
                                @if ($errors->has('category'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('category') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        {{-- //Category --}}


 @if (gp247_store_check_multi_partner_installed() ||  gp247_store_check_multi_store_installed())
                        {{-- select shop_store --}}
                        @php
                        if (function_exists('gp247_get_list_store_of_product_detail')) {
                            $oldData = gp247_get_list_store_of_product_detail($product->id);
                        } else {
                            $oldData = null;
                        }
                        $listStore = [];
                        $shop_store = old('shop_store', $oldData);
                        if(is_array($shop_store)){
                            foreach($shop_store as $value){
                                $listStore[] = $value;
                            }
                        }
                        @endphp

                        <div class="form-group row {{ $errors->has('shop_store') ? ' text-red' : '' }}">
                            <label for="shop_store"
                                class="col-sm-2 col-form-label">{{ gp247_language_render('admin.select_store') }}</label>
                            <div class="col-sm-8">
                                <select class="form-control shop_store select2" 
                                @if (gp247_store_check_multi_store_installed())
                                    multiple="multiple"
                                @endif
                                data-placeholder="{{ gp247_language_render('admin.select_store') }}" style="width: 100%;"
                                name="shop_store[]">
                                    <option value=""></option>
                                    @foreach (gp247_store_get_list_code() as $k => $v)
                                    <option value="{{ $k }}"
                                        {{ (count($listStore) && in_array($k, $listStore))?'selected':'' }}>{{ $v }}
                                    </option>
                                    @endforeach
                                </select>
                                @if ($errors->has('shop_store'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('shop_store') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        {{-- //select shop_store --}}
@endif

                        {{-- Images --}}
                        <div class="form-group row  {{ $errors->has('image') ? ' text-red' : '' }}">
                            <label for="image" class="col-sm-2 col-form-label">{{ gp247_language_render('product.image') }}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <input type="text" id="image" name="image"
                                        value="{!! old('image',$product->image) !!}" class="form-control image"
                                        placeholder="" />
                                        <div class="input-group-append">
                                            <a data-input="image" data-preview="preview_image" data-type="product"
                                                class="btn btn-primary lfm">
                                                <i class="fa fa-image"></i> {{gp247_language_render('action.choose_image')}}
                                            </a>
                                        </div>
                                </div>
                                @if ($errors->has('image'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('image') }}
                                </span>
                                @endif
                                <div id="preview_image" class="img_holder">
                                    @if (old('image',$product->image))
                                        <img src="{{ gp247_file(old('image',$product->image)) }}">
                                    @endif
                                </div>
                                @php
                                $listsubImages = old('sub_image',$product->images->pluck('image')->all());
                                @endphp
                                @if (!empty($listsubImages))
                                @foreach ($listsubImages as $key => $sub_image)
                                @if ($sub_image)
                                <div class="group-image">
                                    <div class="input-group"><input type="text" id="sub_image_{{ $key }}"
                                            name="sub_image[]" value="{!! $sub_image !!}"
                                            class="form-control sub_image" placeholder="" /><span
                                            class="input-group-btn"><span><a data-input="sub_image_{{ $key }}"
                                                    data-preview="preview_sub_image_{{ $key }}" data-type="product"
                                                    class="btn btn-flat btn-primary lfm"><i
                                                        class="fa fa-image"></i>
                                                    {{gp247_language_render('action.choose_image')}}</a></span><span
                                                title="Remove" class="btn btn-flat btn-danger removeImage"><i
                                                    class="fa fa-times"></i></span></span></div>
                                    <div id="preview_sub_image_{{ $key }}" class="img_holder"><img
                                            src="{{ gp247_file($sub_image) }}"></div>
                                </div>

                                @endif
                                @endforeach
                                @endif
                                <button type="button" id="add_sub_image" class="btn btn-flat btn-success">
                                    <i class="fa fa-plus" aria-hidden="true"></i>
                                    {{ gp247_language_render('admin.product.add_sub_image') }}
                                </button>

                            </div>
                        </div>
                        {{-- //Images --}}


                        {{-- Sku --}}
                        <div class="form-group row {{ $errors->has('sku') ? ' text-red' : '' }}">
                            <label for="sku" class="col-sm-2 col-form-label">{{ gp247_language_render('product.sku') }}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-pencil-alt"></i></span>
                                    </div>
                                    <input type="text" style="width: 100px;" id="sku" name="sku"
                                        value="{!! old('sku',$product->sku) !!}" class="form-control sku"
                                        placeholder="" />
                                </div>
                                @if ($errors->has('sku'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('sku') }}
                                </span>
                                @else
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ gp247_language_render('product.sku_validate') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        {{-- //Sku --}}


                        {{-- Alias --}}
                        <div class="form-group row {{ $errors->has('alias') ? ' text-red' : '' }}">
                            <label for="alias" class="col-sm-2 col-form-label">{!! gp247_language_render('product.alias') !!}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-pencil-alt"></i></span>
                                    </div>
                                    <input type="text" id="alias" name="alias"
                                        value="{!! old('alias', $product->alias) !!}" class="form-control alias"
                                        placeholder="" />
                                </div>
                                @if ($errors->has('alias'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('alias') }}
                                </span>
                                @else
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ gp247_language_render('product.alias_validate') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        {{-- //Alias --}}

@if (gp247_config_admin('product_brand') && ($product->kind == GP247_PRODUCT_SINGLE || $product->kind == GP247_PRODUCT_BUILD))
                        {{-- Brand --}}
                        <div class="form-group row kind kind0 kind1 {{ $errors->has('brand_id') ? ' text-red' : '' }}">
                            <label for="brand_id" class="col-sm-2 col-form-label">{{ gp247_language_render('product.brand') }}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                <select class="form-control brand_id select2"
                                    name="brand_id">
                                    <option value=""></option>
                                    @foreach ($brands as $k => $v)
                                    <option value="{{ $k }}"
                                        {{ (old('brand_id') ==$k || (!old() && $product->brand_id ==$k) ) ? 'selected':'' }}>
                                        {{ $v->name }}</option>
                                    @endforeach
                                </select>
                                <div class="input-group-append">
                                    <a target=_new href="{{ gp247_route_admin('admin_brand.index') }}" class="btn  btn-flat" title="New">
                                        <i class="fa fa-plus" title="{{ gp247_language_render('action.add') }}"></i>
                                     </a>
                                </div>
                                </div>
                                @if ($errors->has('brand_id'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('brand_id') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        {{-- //Brand --}}
@endif

@if (gp247_config_admin('product_supplier') && ($product->kind == GP247_PRODUCT_SINGLE || $product->kind == GP247_PRODUCT_BUILD))
                        {{-- supplier --}}
                        <div class="form-group row kind kind0 kind1 {{ $errors->has('supplier_id') ? ' text-red' : '' }}">
                            <label for="supplier_id" class="col-sm-2 col-form-label">{{ gp247_language_render('product.supplier') }}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                <select class="form-control supplier_id select2" 
                                    name="supplier_id">
                                    <option value=""></option>
                                    @foreach ($suppliers as $k => $v)
                                    <option value="{{ $k }}"
                                        {{ (old('supplier_id') ==$k || (!old() && $product->supplier_id ==$k) ) ? 'selected':'' }}>
                                        {{ $v->name }}</option>
                                    @endforeach
                                </select>
                                <div class="input-group-append">
                                    <a target=_new href="{{ gp247_route_admin('admin_supplier.index') }}" class="btn  btn-flat" title="New">
                                        <i class="fa fa-plus" title="{{ gp247_language_render('action.add') }}"></i>
                                     </a>
                                </div>
                                </div>
                                @if ($errors->has('supplier_id'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('supplier_id') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        {{-- //supplier --}}
@endif

@if (gp247_config_admin('product_cost') && ($product->kind == GP247_PRODUCT_SINGLE))
                        {{-- Cost --}}
                        <div class="form-group row kind kind0 kind1  {{ $errors->has('cost') ? ' text-red' : '' }}">
                            <label for="cost" class="col-sm-2 col-form-label">{{ gp247_language_render('product.cost') }}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-pencil-alt"></i></span>
                                    </div>
                                    <input type="number" min="0" step="0.01" style="width: 100px;" id="cost" name="cost"
                                        value="{!! old('cost',$product->cost) !!}" class="form-control cost"
                                        placeholder="" />
                                </div>
                                @if ($errors->has('cost'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('cost') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        {{-- //Cost --}}
@endif

@if (gp247_config_admin('product_price') && ($product->kind == GP247_PRODUCT_SINGLE || $product->kind == GP247_PRODUCT_BUILD))
                        {{-- Price --}}
                        <div class="form-group row  {{ $errors->has('price') ? ' text-red' : '' }}">
                            <label for="price" class="col-sm-2 col-form-label">{{ gp247_language_render('product.price') }}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-pencil-alt"></i></span>
                                    </div>
                                    <input type="number" min="0" step="0.01" style="width: 100px;" id="price" name="price"
                                        value="{!! old('price',$product->price) !!}" class="form-control price"
                                        placeholder="" />
                                </div>
                                @if ($errors->has('price'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('price') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        {{-- //Price --}}
@endif

@if ((gp247_config_admin('product_tax') && gp247_config_admin('product_tax') != 'none') && ($product->kind == GP247_PRODUCT_SINGLE || $product->kind == GP247_PRODUCT_BUILD))
                        {{-- Tax --}}
                        <div class="form-group row {{ $errors->has('tax_id') ? ' text-red' : '' }}">
                            <label for="tax_id" class="col-sm-2 col-form-label">{{ gp247_language_render('product.tax') }}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                <select class="form-control tax_id select2"
                                    name="tax_id">
                                    @foreach ($taxs as $k => $v)
                                    <option value="{{ $k }}"
                                        {{ (old('tax_id') ==$k || (!old() && $product->tax_id ==$k) ) ? 'selected':'' }}>
                                        {{ $v }}</option>
                                    @endforeach
                                </select>
                                <div class="input-group-append">
                                    <a target=_new href="{{ gp247_route_admin('admin_tax.index') }}" class="btn  btn-flat" title="New">
                                        <i class="fa fa-plus" title="{{ gp247_language_render('action.add') }}"></i>
                                     </a>
                                </div>
                                </div>
                                @if ($errors->has('tax_id'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('tax_id') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        {{-- //Tax --}}
@endif


@if (gp247_config_admin('product_promotion') && ($product->kind == GP247_PRODUCT_SINGLE || $product->kind == GP247_PRODUCT_BUILD))
                        {{-- promotion checkbox --}}
                        <div class="form-group row">
                            <label for="promotion_use" class="col-sm-2 col-form-label">{{ gp247_language_render('admin.product.promotion_use') }}</label>
                            <div class="col-sm-1 form-group">
                                <input class="checkbox" type="checkbox" name="promotion_use" id="promotion_use" {{ (old('promotion_use', $product->promotionPrice ? 'on' : '')  ==='on')?'checked':'' }}>
                            </div>
                            
                        {{-- price promotion --}}
                        <div class="form-group row kind promotion-section col-sm-9" style="{{ (old('promotion_use', $product->promotionPrice ? 'on' : '') ==='on')?'':'display: none;' }}">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-pencil-alt"></i></span>
                                            </div>
                                            <input type="number" min="0" step="0.01" id="price_promotion" name="price_promotion" 
                                                value="{!! old('price_promotion',$product->promotionPrice->price_promotion ?? '') !!}" class="form-control input-sm price" 
                                                placeholder="{{ gp247_language_render('product.price_promotion') }}" />
                                        </div>
                                    </div>

                                    <div class="col-sm-4">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-calendar fa-fw"></i></span>
                                            </div>
                                            <input type="text" id="price_promotion_start" name="price_promotion_start"
                                                value="{!!old('price_promotion_start', gp247_datetime_to_date($product->promotionPrice->date_start ?? null))!!}" class="form-control input-sm price_promotion_start date_time" 
                                                data-date-format="yyyy-mm-dd" placeholder="{{ gp247_language_render('product.price_promotion_start') }}" />
                                        </div>
                                    </div>

                                    <div class="col-sm-4">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-calendar fa-fw"></i></span>
                                            </div>
                                            <input type="text" id="price_promotion_end" name="price_promotion_end"
                                                value="{!!old('price_promotion_end', gp247_datetime_to_date($product->promotionPrice->date_end ?? null))!!}" class="form-control input-sm price_promotion_end date_time"
                                                data-date-format="yyyy-mm-dd" placeholder="{{ gp247_language_render('product.price_promotion_end') }}" />
                                        </div>
                                    </div>
                                </div>
                            @if ($errors->has('price_promotion'))
                            <span class="form-text">
                                <i class="fa fa-info-circle"></i> {{ $errors->first('price_promotion') }}
                            </span>
                            @endif
                    </div>
                    {{-- //price promotion --}}
                        </div>

@endif


@if (gp247_config_admin('product_stock') && ($product->kind == GP247_PRODUCT_SINGLE || $product->kind == GP247_PRODUCT_BUILD))
                        {{-- Stock --}}
                        <div class="form-group row {{ $errors->has('stock') ? ' text-red' : '' }}">
                            <label for="stock" class="col-sm-2 col-form-label">{{ gp247_language_render('product.stock') }}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-pencil-alt"></i></span>
                                    </div>
                                    <input type="number" style="width: 100px;" id="stock" name="stock"
                                        value="{!! old('stock',$product->stock) !!}" class="form-control stock"
                                        placeholder="" />
                                </div>
                                @if ($errors->has('stock'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('stock') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        {{-- //Stock --}}
@endif

@if (gp247_config_admin('product_weight') && ($product->kind == GP247_PRODUCT_SINGLE || $product->kind == GP247_PRODUCT_BUILD))
                        {{-- weight --}}
                        <div class="form-group row {{ $errors->has('weight_class') ? ' text-red' : '' }}">
                            <label for="weight_class" class="col-sm-2 col-form-label"><u>{{ gp247_language_render('admin.product.weight_class') }}</u></label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                <select class="form-control weight_class select2"
                                    name="weight_class">
                                    <option value="">{{ gp247_language_render('admin.product.select_weight') }}<option>
                                    @foreach ($listWeight as $v)
                                    <option value="{{ $v }}"
                                        {{ (old('weight_class') == $v || (!old() && $product->weight_class ==$v) ) ? 'selected':'' }}>
                                        {{ $v }}</option>
                                    @endforeach
                                </select>
                                </div>
                                @if ($errors->has('weight_class'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('weight_class') }}
                                </span>
                                @endif
                            </div>
                        </div>


                        <div class="form-group row {{ $errors->has('weight') ? ' text-red' : '' }}">
                            <label for="weight" class="col-sm-2 col-form-label">{{ gp247_language_render('product.weight') }}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-pencil-alt"></i></span>
                                    </div>
                                    <input type="number" min="0" step="0.01" style="width: 100px;" id="weight" name="weight"
                                        value="{!! old('weight',$product->weight) !!}" class="form-control weight"
                                        placeholder="" />
                                </div>
                                @if ($errors->has('weight'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('weight') }}
                                </span>
                                @endif
                            </div>
                        </div>

                        {{-- //weight --}}
@endif


@if (gp247_config_admin('product_length') && ($product->kind == GP247_PRODUCT_SINGLE || $product->kind == GP247_PRODUCT_BUILD))
                        {{-- length --}}
                        <div class="form-group row {{ $errors->has('length_class') ? ' text-red' : '' }}">
                            <label for="length_class" class="col-sm-2 col-form-label"><u>{{ gp247_language_render('admin.product.length_class') }}</u></label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                <select class="form-control length_class select2"
                                    name="length_class">
                                    <option value="">{{ gp247_language_render('admin.product.select_length') }}<option>
                                    @foreach ($listLength as  $v)
                                    <option value="{{ $v }}"
                                        {{ (old('length_class') == $v || (!old() && $product->length_class ==$v) ) ? 'selected':'' }}>
                                        {{ $v }}</option>
                                    @endforeach
                                </select>
                                </div>
                                @if ($errors->has('length_class'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('length_class') }}
                                </span>
                                @endif
                            </div>
                        </div>


                        <div class="form-group row {{ $errors->has('length') ? ' text-red' : '' }}">
                            <label for="length" class="col-sm-2 col-form-label">{{ gp247_language_render('product.length') }}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-pencil-alt"></i></span>
                                    </div>
                                    <input type="number" min="0" step="0.01" style="width: 100px;" id="length" name="length"
                                        value="{!! old('length',$product->length) !!}" class="form-control length"
                                        placeholder="" />
                                </div>
                                @if ($errors->has('length'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('length') }}
                                </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row {{ $errors->has('height') ? ' text-red' : '' }}">
                            <label for="height" class="col-sm-2 col-form-label">{{ gp247_language_render('product.height') }}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-pencil-alt"></i></span>
                                    </div>
                                    <input type="number" min="0" step="0.01" style="width: 100px;" id="height" name="height"
                                        value="{!! old('height',$product->height) !!}" class="form-control height"
                                        placeholder="" />
                                </div>
                                @if ($errors->has('height'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('height') }}
                                </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row {{ $errors->has('width') ? ' text-red' : '' }}">
                            <label for="width" class="col-sm-2 col-form-label">{{ gp247_language_render('product.width') }}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-pencil-alt"></i></span>
                                    </div>
                                    <input type="number" min="0" step="0.01" style="width: 100px;" id="width" name="width"
                                        value="{!! old('width',$product->width) !!}" class="form-control width"
                                        placeholder="" />
                                </div>
                                @if ($errors->has('width'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('width') }}
                                </span>
                                @endif
                            </div>
                        </div>

                        {{-- //length --}}
@endif


@if (gp247_config_admin('product_tag') && ($product->kind == GP247_PRODUCT_SINGLE))
                        <div class="form-group row kind kind0 kind1  {{ $errors->has('tag') ? ' text-red' : '' }}">
                            <label for="tag" class="col-sm-2 col-form-label">{{ gp247_language_render('product.tag') }}</label>
                            <div class="col-sm-8">
                                <div class="icheck-primary d-inline">
                                    <input type="radio" id="radioPrimary" name="tag" value="" {{ (old('tag', '') == '')?'checked':'' }}>
                                    <label for="radioPrimary">
                                        None
                                    </label>
                                </div>
                                @foreach ( $tags as $tag)
                                <div class="icheck-primary d-inline">
                                    <input type="radio" id="radioPrimary{{ $tag }}" name="tag" value="{{ $tag }}"  {{ (old('tag', $product->tag) == $tag)?'checked':'' }}>
                                    <label for="radioPrimary{{ $tag }}">
                                        {{ $tag }}
                                    </label>
                                </div>
                                @endforeach
                                <div class="icheck-primary d-inline">
                                <label>
                                    <a target=_new href="{{ gp247_route_admin('admin_product_tag.index') }}" title="New">
                                        <i class="fa fa-plus" title="{{ gp247_language_render('action.add') }}"></i>
                                    </a>
                                </label>
                                </div>
                                @if ($errors->has('tag'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('tag') }}
                                </span>
                                @endif

                            </div>
                        </div>
@endif

@if (gp247_config_admin('product_available') && ($product->kind == GP247_PRODUCT_SINGLE || $product->kind == GP247_PRODUCT_BUILD))
                        {{-- Date vailalable --}}
                        <div
                            class="form-group row kind kind0 kind1  {{ $errors->has('date_available') ? ' text-red' : '' }}">
                            <label for="date_available"
                                class="col-sm-2 col-form-label">{{ gp247_language_render('product.date_available') }}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-calendar fa-fw"></i></span>
                                    </div>
                                    <input type="text" style="width: 100px;" id="date_available" name="date_available"
                                        value="{!!old('date_available',$product->date_available)!!}"
                                        class="form-control date_available date_time" data-date-format="yyyy-mm-dd" placeholder="yyyy-mm-dd" />
                                </div>
                                @if ($errors->has('date_available'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('date_available') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        {{-- //Date vailalable --}}
@endif


@if (($product->kind == GP247_PRODUCT_SINGLE || $product->kind == GP247_PRODUCT_BUILD))
                            
                        {{-- minimum --}}
                        <div class="form-group row kind kind0 kind1  {{ $errors->has('minimum') ? ' text-red' : '' }}">
                            <label for="minimum" class="col-sm-2 col-form-label">{{ gp247_language_render('product.minimum') }}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-pencil-alt"></i></span>
                                    </div>
                                    <input type="number" style="width: 100px;" id="minimum" name="minimum"
                                        value="{!! old('minimum',$product['minimum']) !!}" class="form-control minimum"
                                        placeholder="" />
                                </div>
                                @if ($errors->has('minimum'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('minimum') }}
                                </span>
                                @else
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ gp247_language_render('product.minimum_help') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        {{-- //minimum --}}
@endif

                        {{-- Sort --}}
                        <div class="form-group row  {{ $errors->has('sort') ? ' text-red' : '' }}">
                            <label for="sort" class="col-sm-2 col-form-label">{{ gp247_language_render('admin.product.sort') }}</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-pencil-alt"></i></span>
                                    </div>
                                    <input type="number" min="0" style="width: 100px;" id="sort" name="sort"
                                        value="{!! old('sort',$product['sort']) !!}" class="form-control sort"
                                        placeholder="" />
                                </div>
                                @if ($errors->has('sort'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('sort') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        {{-- //Sort --}}

                        {{-- Status --}}
                        <div class="form-group row ">
                            <label for="status" class="col-sm-2 col-form-label">{{ gp247_language_render('product.status') }}</label>
                            <div class="col-sm-8">
                                <input class="checkbox" type="checkbox" name="status" {{ old('status',$product['status'])?'checked':''}}>
                            </div>
                        </div>
                        {{-- //Status --}}

                        {{-- Approve --}}
                        <div class="form-group row ">
                            <label for="approve" class="col-sm-2 col-form-label">{{ gp247_language_render('product.approve') }}</label>
                            <div class="col-sm-8">
                                <input class="checkbox" type="checkbox" name="approve" {{ old('approve',$product['approve'])?'checked':''}}>
                            </div>
                        </div>
                        {{-- //Approve --}}

@if (gp247_config_admin('product_kind'))
                        @if ($product->kind == GP247_PRODUCT_GROUP)
                        {{-- List product in groups --}}
                        <hr>
                        <div class="form-group row {{ $errors->has('productInGroup') ? ' text-red' : '' }}">
                            <label class="col-sm-2 col-form-label"></label>
                            <div class="col-sm-8"><label>{{ gp247_language_render('admin.product.select_product_in_group') }}</label>
                            </div>
                        </div>
                        <div class="form-group row {{ $errors->has('productInGroup') ? ' text-red' : '' }}">
                            <div class="col-sm-2">
                            </div>
                            <div class="col-sm-8">
                                @php
                                $listgroups= [];
                                $groups = old('productInGroup',$product->groups->pluck('product_id')->toArray());
                                if(is_array($groups)){
                                foreach($groups as $value){
                                $listgroups[] = $value;
                                }
                                }
                                @endphp
                                @if ($listgroups)
                                @foreach ($listgroups as $pID)
                                @if ($pID)
                                @php
                                $newHtml = str_replace('value="'.$pID.'"', 'value="'.$pID.'" selected',
                                $htmlSelectGroup);
                                @endphp
                                {!! $newHtml !!}
                                @endif
                                @endforeach
                                @endif
                                <div id="position_group_flag"></div>
                                @if ($errors->has('productInGroup'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('productInGroup') }}
                                </span>
                                @endif
                                <button type="button" id="add_product_in_group" class="btn btn-flat btn-success">
                                    <i class="fa fa-plus" aria-hidden="true"></i>
                                    {{ gp247_language_render('admin.product.add_product') }}
                                </button>
                                @if ($errors->has('productInGroup'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('productInGroup') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        {{-- //end List product in groups --}}
                        @endif



                        @if ($product->kind == GP247_PRODUCT_BUILD)
                        <hr>
                        {{-- List product build --}}
                        <div class="form-group row {{ $errors->has('productBuild') ? ' text-red' : '' }}">
                            <label class="col-sm-2 col-form-label"></label>
                            <div class="col-sm-8">
                                <label>{{ gp247_language_render('admin.product.select_product_in_build') }}</label>
                            </div>
                        </div>

                        <div class="form-group row {{ $errors->has('productBuild') ? ' text-red' : '' }}">
                            <div class="col-sm-2">
                            </div>
                            <div class="col-sm-8">
                                <div class="row"></div>

                                @php
                                $listBuilds= [];
                                $groups = old('productBuild',$product->builds->pluck('product_id')->toArray());
                                $groupsQty = old('productBuildQty',$product->builds->pluck('quantity')->toArray());
                                if(is_array($groups)){
                                foreach($groups as $key => $value){
                                $listBuilds[] = $value;
                                $listBuildsQty[] = $groupsQty[$key];
                                }
                                }
                                @endphp

                                @if ($listBuilds)
                                @foreach ($listBuilds as $key => $pID)
                                @if ($pID && $listBuildsQty[$key])
                                @php
                                $newHtml = str_replace('value="'.$pID.'"', 'value="'.$pID.'" selected',
                                $htmlSelectBuild);
                                $newHtml = str_replace('name="productBuildQty[]" value="1" min=1',
                                'name="productBuildQty[]" value="'.$listBuildsQty[$key].'"', $newHtml);
                                @endphp
                                {!! $newHtml !!}
                                @endif
                                @endforeach
                                @endif
                                <div id="position_build_flag"></div>
                                @if ($errors->has('productBuild'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('productBuild') }}
                                </span>
                                @endif
                                <button type="button" id="add_product_in_build" class="btn btn-flat btn-success">
                                    <i class="fa fa-plus" aria-hidden="true"></i>
                                    {{ gp247_language_render('admin.product.add_product') }}
                                </button>
                                @if ($errors->has('productBuild') || $errors->has('productBuildQty'))
                                <span class="form-text">
                                    <i class="fa fa-info-circle"></i> {{ $errors->first('productBuild') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        {{-- //end List product build --}}
                        @endif
@endif


@if (gp247_config_admin('product_attribute') && ($product->kind == GP247_PRODUCT_SINGLE))
                        {{-- List product attributes --}}
                        <hr>
                        @if (!empty($attributeGroup))
                        <div class="form-group row">
                            <div class="col-sm-2">
                                <label>{{ gp247_language_render('product.attribute') }} (<a href="{{ gp247_route_admin('admin_attribute_group.index') }}"><i class="fa fa-plus" aria-hidden="true"></i></a>)</label>
                            </div>
                            <div class="col-sm-8">

                                @php
                                $getDataAtt = $product->attributes->groupBy('attribute_group_id')->toArray();
                                $arrAtt = [];
                                foreach ($getDataAtt as $groupId => $row) {
                                    foreach ($row as $key => $value) {
                                        $arrAtt[$groupId]['name'][] = $value['name'];
                                        $arrAtt[$groupId]['add_price'][] = $value['add_price'];
                                    }
                                }
                                $dataAtt = old('attribute', $arrAtt);
                                @endphp

                                @foreach ($attributeGroup as $attGroupId => $attName)
                                    <table width="100%">
                                        <tr>
                                            <td colspan="3"><p><b>{{ $attName }}:</b></p></td>
                                        </tr>
                                        <tr>
                                            <td>{{ gp247_language_render('admin.product.add_attribute_place') }}</td>
                                            <td>{{ gp247_language_render('admin.product.add_price_place') }}</td>
                                        </tr>
                                    @if (!empty($dataAtt[$attGroupId]['name']))
                                        @foreach ($dataAtt[$attGroupId]['name'] as $key => $attValue)
                                            @php
                                            $newHtml = str_replace('attribute_group', $attGroupId, $htmlProductAtrribute);
                                            $newHtml = str_replace('attribute_value', $attValue, $newHtml);
                                            $newHtml = str_replace('add_price_value', $dataAtt[$attGroupId]['add_price'][$key], $newHtml);
                                            @endphp
                                            {!! $newHtml !!}
                                        @endforeach
                                    @endif
                                        <tr>
                                            <td colspan="3"><br><button type="button"
                                                    class="btn btn-flat btn-success add_attribute"
                                                    data-id="{{ $attGroupId }}">
                                                    <i class="fa fa-plus" aria-hidden="true"></i>
                                                    {{ gp247_language_render('admin.product.add_attribute') }}
                                                </button><br><br></td>
                                        </tr>
                                    </table>
                                @endforeach
                            </div>
                        </div>
                        {{-- //end List product attributes --}}
                        @endif
@endif


                    {{-- Custom fields --}}
                    @includeIf('gp247-core::component.render_form_custom_field', ['object' => $product])
                    {{-- //Custom fields --}}


                </div>
                <!-- /.card-body -->

                <div class="card-footer kind kind0  kind1 kind2 row" id="card-footer">
                    @csrf
                    <div class="col-md-2">
                    </div>

                    <div class="col-md-8">
                        <div class="btn-group float-right">
                            <button type="submit" class="btn btn-primary">{{ gp247_language_render('action.submit') }}</button>
                        </div>

                        <div class="btn-group float-left">
                            <button type="reset" class="btn btn-warning">{{ gp247_language_render('action.reset') }}</button>
                        </div>
                    </div>
                </div>

                <!-- /.card-footer -->
            </form>
        </div>
    </div>
</div>



@endsection

@push('styles')

<style>
    #start-add {
        margin: 20px;
    }
    #start-add.kind-0 {
        background-color: #fff;
        padding: 10px;
        font-size: 20px;
        color: black;
    }
    #start-add.kind-1 {
        background-color: #17a2b8;
        padding: 10px;
        font-size: 20px;
        color: white;
    }
    #start-add.kind-2 {
        background-color: #ffc107;
        padding: 10px;
        font-size: 20px;
        color: white;
    }
</style>

@endpush

@push('scripts')
@include('gp247-core::component.ckeditor_js')


<script type="text/javascript">

$("[name='tag']").change(function() {
    if($(this).val() == '{{ GP247_TAG_DOWNLOAD }}') {
        $('#download_path').show();
    } else {
        $('#download_path').hide();
    }
});

// Add sub images
var id_sub_image = {{ old('sub_image')?count(old('sub_image')):0 }};
$('#add_sub_image').click(function(event) {
    id_sub_image +=1;
    $(this).before(
    '<div class="group-image">'
    +'<div class="input-group">'
    +'  <input type="text" id="sub_image_'+id_sub_image+'" name="sub_image[]" value="" class="form-control sub_image" placeholder=""  />'
    +'  <div class="input-group-append">'
    +'  <span data-input="sub_image_'+id_sub_image+'" data-preview="preview_sub_image_'+id_sub_image+'" data-type="product" class="btn btn-flat btn-primary lfm">'
    +'      <i class="fa fa-image"></i> {{gp247_language_render('action.choose_image')}}'
    +'  </span>'
    + ' </div>'
    +'<span title="Remove" class="btn btn-flat btn-danger removeImage"><i class="fa fa-times"></i></span>'
    +'</div>'
    +'<div id="preview_sub_image_'+id_sub_image+'" class="img_holder"></div>'
    +'</div>');
    $('.removeImage').click(function(event) {
        $(this).closest('div').remove();
    });
    $('.lfm').filemanager();
});
    $('.removeImage').click(function(event) {
        $(this).closest('.group-image').remove();
    });
//end sub images


@if (gp247_config_admin('product_kind') && $product->kind == GP247_PRODUCT_GROUP)
// Select product in group
$('#add_product_in_group').click(function(event) {
    var htmlSelectGroup = '{!! str_replace("\n","", $htmlSelectGroup) !!}';
    $(this).before(htmlSelectGroup);
    $('.select2').select2();
    $('.removeproductInGroup').click(function(event) {
        $(this).closest('table').remove();
    });
});
$('.removeproductInGroup').click(function(event) {
    $(this).closest('table').remove();
});
//end select in group
@endif

@if (gp247_config_admin('product_kind') && $product->kind == GP247_PRODUCT_BUILD)
// Select product in build
$('#add_product_in_build').click(function(event) {
    var htmlSelectBuild = '{!! str_replace("\n", "", $htmlSelectBuild) !!}';
    $(this).before(htmlSelectBuild);
    $('.select2').select2();
    $('.removeproductBuild').click(function(event) {
        $(this).closest('table').remove();
    });
});
$('.removeproductBuild').click(function(event) {
    $(this).closest('table').remove();
});
//end select in build
@endif

// Select product attributes
$('.add_attribute').click(function(event) {
    var htmlProductAtrribute = '{!! $htmlProductAtrribute ?? '' !!}';
    var attGroup = $(this).attr("data-id");
    htmlProductAtrribute = htmlProductAtrribute.replace(/attribute_group/gi, attGroup);
    htmlProductAtrribute = htmlProductAtrribute.replace("attribute_value", "");
    htmlProductAtrribute = htmlProductAtrribute.replace("add_price_value", "0");
    $(this).closest('tr').before(htmlProductAtrribute);
    $('.removeAttribute').click(function(event) {
        $(this).closest('tr').remove();
    });
});
$('.removeAttribute').click(function(event) {
    $(this).closest('tr').remove();
});
//end select attributes

$('textarea.editor').ckeditor(
    {
        filebrowserImageBrowseUrl: '{{ gp247_route_admin('admin.home').'/'.config('lfm.url_prefix') }}?type=product',
        filebrowserImageUploadUrl: '{{ gp247_route_admin('admin.home').'/'.config('lfm.url_prefix') }}/upload?type=product&_token={{csrf_token()}}',
        filebrowserBrowseUrl: '{{ gp247_route_admin('admin.home').'/'.config('lfm.url_prefix') }}?type=files',
        filebrowserUploadUrl: '{{ gp247_route_admin('admin.home').'/'.config('lfm.url_prefix') }}/upload?type=file&_token={{csrf_token()}}',
        filebrowserWindowWidth: '900',
        filebrowserWindowHeight: '500'
    }
);

// Handle promotion section visibility
$(document).ready(function() {
    $('#promotion_use').on('ifChecked', function(event){
        $('.promotion-section').show();
    });

    $('#promotion_use').on('ifUnchecked', function(event){
        $('.promotion-section').hide();
    });

});
</script>

@endpush