   {{-- Quikc order --}}
   @if (gp247_config_global('MultiVendorPro_quick_order') && gp247_config_global('MultiVendorPro'))
   <div id="quick-order"><span class="fa fa-cog fa-spin"></span> <a href="{{ gp247_route_front('MultiVendorPro.quick_order', ['code' => $storeCode ?? '']) }}">{{ gp247_language_render('multi_vendor.quick_order') }}</a></div>
   <style>
     #quick-order {
       font-size: 20px;
       position: fixed;
       right: 10px;
       bottom: 30%;
       background: #0c0c0cd4;
       border-radius: 5px;
       font-weight: 600;
       text-align: center;
       color: #ffffff;
       margin: 0 auto;
       padding: 10px;
     }
   </style>
   @endif
  {{--// Quikc order --}}