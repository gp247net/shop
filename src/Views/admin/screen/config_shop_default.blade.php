@extends('gp247-core::layout')
@section('main')
      <div class="card card-primary card-outline card-outline-tabs">
        <div class="card-header p-0 border-bottom-0">
          <ul class="nav nav-tabs" id="custom-tabs-four-tab" role="tablist">
            
            <li class="nav-item">
              <a class="nav-link active" id="tab-admin-product-tab" data-toggle="pill" href="#tab-admin-product" role="tab" aria-controls="tab-admin-product" aria-selected="false"><i class="fas fa-cog"></i> {{ gp247_language_render('admin.shop.config_product') }}</a>
            </li>

            <li class="nav-item">
              <a class="nav-link" id="tab-admin-customer-tab" data-toggle="pill" href="#tab-admin-customer" role="tab" aria-controls="tab-admin-customer" aria-selected="false"><i class="fas fa-cog"></i> {{ gp247_language_render('admin.shop.config_customer') }}</a>
            </li>

            <li class="nav-item">
              <a class="nav-link" id="tab-admin-order-tab" data-toggle="pill" href="#tab-admin-order" role="tab" aria-controls="tab-admin-order" aria-selected="false"><i class="fas fa-cog"></i> {{ gp247_language_render('admin.shop.config_order') }}</a>
            </li>

            <li class="nav-item">
              <a class="nav-link" id="tab-admin-sendmail-tab" data-toggle="pill" href="#tab-admin-sendmail" role="tab" aria-controls="tab-admin-sendmail" aria-selected="false"><i class="fas fa-map-pin"></i> {{ gp247_language_render('admin.shop.config_sendmail') }}</a>
            </li>

            <li class="nav-item">
              <a class="nav-link" id="tab-admin-limit-data-tab" data-toggle="pill" href="#tab-admin-limit-data" role="tab" aria-controls="tab-admin-limit-data" aria-selected="false"><i class="fas fa-map-pin"></i> {{ gp247_language_render('admin.shop.config_limit_per_page') }}</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="tab-admin-layout-tab" data-toggle="pill" href="#tab-admin-layout" role="tab" aria-controls="tab-admin-layout" aria-selected="false"><i class="fas fa-map-pin"></i> {{ gp247_language_render('admin.shop.config_layout') }}</a>
            </li>


            <li class="nav-item">
              <a class="nav-link" id="tab-admin-captcha-tab" data-toggle="pill" href="#tab-admin-captcha" role="tab" aria-controls="tab-admin-captcha" aria-selected="false"><i class="fas fa-map-pin"></i> {{ gp247_language_render('admin.shop.config_captcha') }}</a>
            </li>
          </ul>
        </div>
        
        <div class="card-body">
          <div class="tab-content" id="custom-tabs-four-tabContent">

              {{-- Tab admin product --}}
              <div class="tab-pane fade active show" id="tab-admin-product" role="tabpanel" aria-labelledby="tab-admin-product-tab">
                @include('gp247-shop-admin::screen.config_shop.config_product')
              </div>
              {{-- // admin product --}}

            {{-- Tab admin customer --}}
            <div class="tab-pane fade" id="tab-admin-customer" role="tabpanel" aria-labelledby="tab-admin-customer-tab">
              @include('gp247-shop-admin::screen.config_shop.config_customer')
            </div>
            {{-- // admin customer --}}

            
              {{-- Tab admin order --}}
              <div class="tab-pane fade" id="tab-admin-order" role="tabpanel" aria-labelledby="tab-admin-order-tab">
                @include('gp247-shop-admin::screen.config_shop.config_order')
              </div>
              {{-- // admin order --}}

              {{-- Tab admin sendmail --}}
              <div class="tab-pane fade" id="tab-admin-sendmail" role="tabpanel" aria-labelledby="tab-admin-sendmail-tab">
                @include('gp247-shop-admin::screen.config_shop.config_sendmail')
              </div>
              {{-- // admin sendmail --}}


            {{-- Tab admin display --}}
            <div class="tab-pane fade" id="tab-admin-limit-data" role="tabpanel" aria-labelledby="tab-admin-limit-data-tab">
              @include('gp247-shop-admin::screen.config_shop.config_limit_per_page')
            </div>
            {{-- // admin config --}}

              {{-- Tab admin config layout --}}
              <div class="tab-pane fade" id="tab-admin-layout" role="tabpanel" aria-labelledby="tab-admin-layout-tab">
                @include('gp247-shop-admin::screen.config_shop.config_layout')
              </div>
              {{-- // admin config layout --}}


              {{-- Tab admin captcha --}}
              <div class="tab-pane fade" id="tab-admin-captcha" role="tabpanel" aria-labelledby="tab-admin-captcha-tab">
                @include('gp247-shop-admin::screen.config_shop.config_captcha')
              </div>
              {{-- // admin captcha --}}
          </div>
        </div>
        <!-- /.card -->
</div>

@endsection

@push('styles')
<!-- Ediable -->
<link rel="stylesheet" href="{{ gp247_file('GP247/Core/plugin/bootstrap-editable.css')}}">
<style type="text/css">
  #maintain_content img{
    max-width: 100%;
  }
</style>
@endpush

@if (empty($dataNotFound))
@push('scripts')
<!-- Ediable -->
<script src="{{ gp247_file('GP247/Core/plugin/bootstrap-editable.min.js')}}"></script>

<script type="text/javascript">

  // Editable
$(document).ready(function() {

      //  $.fn.editable.defaults.mode = 'inline';
      $.fn.editable.defaults.params = function (params) {
        params._token = "{{ csrf_token() }}";
        params.storeId = "{{ $storeId }}";
        return params;
      };

      $('.editable-required').editable({
        validate: function(value) {
            if (value == '') {
                return '{{  gp247_language_render('admin.not_empty') }}';
            }
        },
        success: function(data) {
          if(data.error == 0){
            alertJs('success', '{{ gp247_language_render('admin.msg_change_success') }}');
          } else {
            alertJs('error', data.msg);
          }
      }
    });

    $('.editable').editable({
        validate: function(value) {
        },
        success: function(data) {
          console.log(data);
          if(data.error == 0){
            alertJs('success', '{{ gp247_language_render('admin.msg_change_success') }}');
          } else {
            alertMsg('error', data.msg);
          }
      }
    });

});


$('input.check-data-config').iCheck({
    checkboxClass: 'icheckbox_square-blue',
    radioClass: 'iradio_square-blue',
    increaseArea: '20%' /* optional */
  }).on('ifChanged', function(e) {
  var isChecked = e.currentTarget.checked;
  isChecked = (isChecked == false)?0:1;
  var name = $(this).attr('name');
    $.ajax({
      url: '{{ $urlUpdateConfig }}',
      type: 'POST',
      dataType: 'JSON',
      data: {
          "_token": "{{ csrf_token() }}",
          "name": $(this).attr('name'),
          "storeId": $(this).data('store'),
          "value": isChecked
        },
    })
    .done(function(data) {
      if(data.error == 0){
        alertJs('success', '{{ gp247_language_render('admin.msg_change_success') }}');
      } else {
        alertJs('error', data.msg);
      }
    });

    });

  $('input.check-data-config-global').iCheck({
    checkboxClass: 'icheckbox_square-blue',
    radioClass: 'iradio_square-blue',
    increaseArea: '20%' /* optional */
  }).on('ifChanged', function(e) {
  var isChecked = e.currentTarget.checked;
  isChecked = (isChecked == false)?0:1;
  var name = $(this).attr('name');
    $.ajax({
      url: '{{ $urlUpdateConfigGlobal }}',
      type: 'POST',
      dataType: 'JSON',
      data: {
          "_token": "{{ csrf_token() }}",
          "name": $(this).attr('name'),
          "value": isChecked
        },
    })
    .done(function(data) {
      if(data.error == 0){
        if (isChecked == 0) {
          $('#smtp-config').hide();
        } else {
          $('#smtp-config').show();
        }
        alertJs('success', '{{ gp247_language_render('admin.msg_change_success') }}');
      } else {
        alertJs('error', data.msg);
      }
    });

    });


</script>




<script>
  // Update store_info

//End update store_info
</script>

@endpush
@endif