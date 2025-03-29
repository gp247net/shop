@extends($GP247TemplatePath.'.layout')

@section('block_main')
<section class="section section-sm section-first bg-default text-md-left">
    <div class="container">
      <div class="row">
        <div class="col-12 col-sm-12 col-md-3">
          @php
              $view = gp247_shop_process_view($GP247TemplatePath, 'account.nav_customer');
          @endphp
          @include($view)
        </div>
        <div class="col-12 col-sm-12 col-md-9 min-height-37vh">
            @section('block_main_profile')
            @show
        </div>
      </div>
    </div>
</section>
@endsection