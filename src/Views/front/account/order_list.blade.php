@php
/*
$layout_page = shop_profile
** Variables:**
- $statusOrder
- $orders
*/ 
@endphp

@php
    $view = gp247_shop_process_view($GP247TemplatePath, 'account.layout');
@endphp
@extends($view)

@section('block_main_profile')
<h6 class="title-store">{{ $title }}</h6>
      @if (count($orders) ==0)
      <div class="text-danger">
        {{ gp247_language_render('front.no_item') }}
      </div>
      @else
      <table class="table box table-bordered" width="100%">
        <thead>
          <tr>
            <th style="width: 50px;">No.</th>
            <th style="width: 100px;">ID</th>
            <th>{{ gp247_language_render('order.total') }}</th>
            <th>{{ gp247_language_render('order.order_status') }}</th>
            <th>{{ gp247_language_render('common.created_at') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($orders as $order)
          @php
          $n = (isset($n)?$n:0);
          $n++;
          @endphp
          <tr>
            <td><span class="item_21_id">{{ $n }}</span></td>
            <td><span class="item_21_sku">#{{ $order->id }}</span></td>
            <td align="right">
              {{ number_format($order->total) }}
            </td>
            <td>{{ $statusOrder[$order->status]}}</td>
            <td>{{ $order->created_at }}</td>
            <td>
              <a href="{{ gp247_route_front('customer.order_detail', ['id' => $order->id ]) }}"><i class="fa fa-indent" aria-hidden="true"></i> {{ gp247_language_render('order.detail') }}</a>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
@endsection