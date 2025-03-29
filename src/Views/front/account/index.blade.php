@php
/*
$layout_page = shop_profile
** Variables:**
- $customer
*/ 
@endphp

@php
    $view = gp247_shop_process_view($GP247TemplatePath, 'account.layout');
@endphp
@extends($view)

@section('block_main_profile')
    <p>Wellcome <span> {{ $customer['first_name'] }} {{ $customer['last_name'] }}</span>!</p>
@endsection