{{-- Use gp247_config with storeId, dont use gp247_config_admin because will switch the store to the specified store Id
--}}

<div class="row">

  <div class="col-md-6">

    <div class="card">
      <div class="card-body table-responsivep-0 card-primary">
       <table class="table table-hover box-body text-wrap table-bordered">
         <tbody>
           @foreach ($orderConfig as $config)
             <tr>
               <td>{{ gp247_language_render($config->detail) }}</td>
               <td><input class="check-data-config-global" data-store="{{ $storeId }}"  type="checkbox" name="{{ $config->key }}"  {{ $config->value?"checked":"" }}></td>
             </tr>
           @endforeach
         </tbody>
       </table>
      </div>
    </div>
  </div>


</div>