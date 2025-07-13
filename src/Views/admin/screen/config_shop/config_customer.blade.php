{{-- Use gp247_config with storeId, dont use gp247_config_admin because will switch the store to the specified store Id
--}}
{{-- Use gp247_config with storeId, dont use gp247_config_admin because will switch the store to the specified store Id
--}}

<div class="row">
  <div class="col-md-5">
    <div class="card">
      <div class="card-header with-border">
        <h3 class="card-title">{{ gp247_language_render('admin.customer.setting_info') }}</h3>
      </div>

      <div class="card-body table-responsivep-0">
       <table class="table table-hover box-body text-wrap table-bordered">
         <tbody>
           @foreach ($customerConfigsDefault as $config)
           <tr>
            <td>{{ gp247_language_render($config['detail']) }}</td>
            <td><input class="check-data-config-global" data-store="{{ $storeId }}"  type="checkbox" name="{{ $config['key'] }}"  {{ $config['value']?"checked":"" }}></td>
          </tr>
           @endforeach
         </tbody>
       </table>
      </div>
    </div>
  </div>

  <div class="col-md-7">
  <div class="card">

    <div class="card-body table-responsivep-0">
    <table class="table table-hover box-body text-wrap table-bordered">
      <thead>
        <tr>
          <th>{{ gp247_language_render('admin.customer.field') }}</th>
          <th>{{ gp247_language_render('admin.customer.value') }}</th>
          <th>{{ gp247_language_render('admin.customer.required') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($customerConfigsAttribute as $key => $customerConfig)
          <tr>
            <td>{{ gp247_language_render($customerConfig['detail']) }}</td>
            <td><input class="check-data-config-global" data-store="{{ $storeId }}" type="checkbox" name="{{ $customerConfig['key'] }}"  {{ $customerConfig['value']?"checked":"" }}></td>
            <td>
              @if (!empty($customerConfigsAttributeRequired[$key.'_required']))
              <input class="check-data-config-global" data-store="{{ $storeId }}" type="checkbox" name="{{ $customerConfigsAttributeRequired[$key.'_required']['key'] }}"  {{ $customerConfigsAttributeRequired[$key.'_required']['value']?"checked":"" }}>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
    </div>
  </div>
</div>
</div>