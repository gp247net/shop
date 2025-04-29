@php
    $totalOrder = \GP247\Shop\Admin\Models\AdminOrder::getTotalOrder();
    $totalProduct = \GP247\Shop\Admin\Models\AdminProduct::getTotalProduct();
    $totalCustomer = \GP247\Shop\Admin\Models\AdminCustomer::getTotalCustomer();
@endphp
<div class="card">
  <div class="row">
  <div class="col-md-4 col-sm-6 col-xs-12">
    <div class="info-box">
      <span class="info-box-icon bg-green"><i class="fas fa-shopping-cart"></i></span>

      <div class="info-box-content">
        <span class="info-box-text">{{ gp247_language_render('admin.dashboard.total_order') }}</span>
        <span class="info-box-number">{{ number_format($totalOrder ?? 0) }}</span>
        <a href="{{ gp247_route_admin('admin_order.index') }}" class="small-box-footer">
          {{ gp247_language_render('action.view_more') }}&nbsp;
          <i class="fa fa-arrow-circle-right"></i>
        </a>
      </div>
      <!-- /.info-box-content -->
    </div>
    <!-- /.info-box -->
  </div>


  <div class="col-md-4 col-sm-6 col-xs-12">
    <div class="info-box">
      <span class="info-box-icon bg-aqua"><i class="fa fa-tags"></i></span>

      <div class="info-box-content">
        <span class="info-box-text">{{ gp247_language_render('admin.dashboard.total_product') }}</span>
        <span class="info-box-number">{{ number_format($totalProduct ?? 0) }}</span>
        <a href="{{ gp247_route_admin('admin_product.index') }}" class="small-box-footer">
            {{ gp247_language_render('action.view_more') }}&nbsp;
            <i class="fa fa-arrow-circle-right"></i>
        </a>

      </div>
      <!-- /.info-box-content -->
    </div>
    <!-- /.info-box -->
  </div>


  <!-- /.col -->
  <div class="col-md-4 col-sm-6 col-xs-12">
    <div class="info-box">
      <span class="info-box-icon bg-yellow"><i class="fas fa-user "></i></span>

      <div class="info-box-content">
        <span class="info-box-text">{{ gp247_language_render('admin.dashboard.total_customer') }}</span>
        <span class="info-box-number">{{ number_format($totalCustomer ?? 0) }}</span>
        <a href="{{ gp247_route_admin('admin_customer.index') }}" class="small-box-footer">
            {{ gp247_language_render('action.view_more') }}&nbsp;
            <i class="fa fa-arrow-circle-right"></i>
        </a>
      </div>
      <!-- /.info-box-content -->
    </div>
    <!-- /.info-box -->
  </div>
  <!-- /.col -->

  <!-- /.col -->
  </div>
</div>