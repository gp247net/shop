@php
    $topCustomer = \GP247\Shop\Admin\Models\AdminCustomer::getTopCustomer();
@endphp
<div class="card">
  <div class="card-header border-transparent">
    <h3 class="card-title">{{ gp247_language_render('admin.dashboard.top_customer_new') }}</h3>

    <div class="card-tools">
      <button type="button" class="btn btn-tool" data-card-widget="collapse">
        <i class="fas fa-minus"></i>
      </button>
      <button type="button" class="btn btn-tool" data-card-widget="remove">
        <i class="fas fa-times"></i>
      </button>
    </div>
  </div>
  <!-- /.card-header -->
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table m-0">
        <tr>
          <th>{{ gp247_language_render('customer.email') }}</th>
          <th>{{ gp247_language_render('customer.name') }}</th>
          <th>{{ gp247_language_render('customer.provider') }}</th>
          <th>{{ gp247_language_render('admin.created_at') }}</th>
        </tr>
        <tbody>
          @if (count($topCustomer ?? []))
          @foreach ($topCustomer as $customer)
            <tr>
              <td><a href="{{ gp247_route_admin('admin_customer.edit',['id'=>$customer->id]) }}">{{ $customer->email }}</a></td>
              <td>{{ $customer->name }}</td>
              <td>{{ $customer->provider }}</td>
              <td>{{ $customer->created_at }}</td>
            </tr>
          @endforeach
        @endif
        </tbody>
      </table>
    </div>
    <!-- /.table-responsive -->
  </div>
  <!-- /.card-body -->
  <div class="card-footer clearfix">
    
  </div>
  <!-- /.card-footer -->
</div>
<!-- /.card -->