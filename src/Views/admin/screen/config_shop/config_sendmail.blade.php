{{-- Use gp247_config with storeId, dont use gp247_config_admin because will switch the store to the specified store Id
--}}

<div class="row">
  <div class="col-md-5">
    <div class="card">
      <div class="card-header with-border">
        <h3 class="card-title">{!! gp247_language_render('admin.shop.config_sendmail_note', ['url' => gp247_route_admin('admin_config.index ')]) !!}</h3>
      </div>

      <div class="card-body table-responsivep-0">
       <table class="table table-hover box-body text-wrap table-bordered">
         <tbody>
           @foreach ($sendmailConfigsDefault as $config)
           <tr>
            <td>{{ gp247_language_render($config['detail']) }}</td>
            <td><input class="check-data-config" data-store="{{ $storeId }}"  type="checkbox" name="{{ $config['key'] }}"  {{ $config['value']?"checked":"" }}></td>
          </tr>
           @endforeach
         </tbody>
       </table>
      </div>
    </div>
  </div>

</div>