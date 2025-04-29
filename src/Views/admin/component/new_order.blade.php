@php
    $topOrder = \GP247\Shop\Admin\Models\AdminOrder::getTopOrder();
@endphp
<div class="card">
  <div class="card-header border-transparent">
    <h3 class="card-title">{{ gp247_language_render('admin.dashboard.top_order_new') }}</h3>

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
        <thead>
          <tr>
            <th>ID</th>
            <th>{{ gp247_language_render('order.email') }}</th>
            <th>{{ gp247_language_render('order.status') }}</th>
            <th>{{ gp247_language_render('admin.created_at') }}</th>
          </tr>
        </thead>
        <tbody>
        @if (count($topOrder ?? []))
          @foreach ($topOrder as $order)
                <tr>
                  <td><a href="{{ gp247_route_admin('admin_order.detail',['id'=>$order->id]) }}">#{{ $order->id }}</a></td>
                  <td>{{ $order->email }}</td>
                  <td><span class="badge badge-{{ $mapStyleStatus[$order->status]??'' }}">{{ $order->orderStatus ? $order->orderStatus->name : $order->status }}</span></td>
                  <td>{{ $order->created_at }}</td>
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