@extends('gp247-shop-front::email.layout')

@section('main')
<h1 style="font-family:Avenir,Helvetica,sans-serif;box-sizing:border-box;color:#2f3133;font-size:19px;font-weight:bold;margin-top:0;text-align:center">{{$title}}</h1>
<p>Hi {{$first_name}} {{$last_name}},</p>
<p>Welcome to my site!</p>
@endsection
