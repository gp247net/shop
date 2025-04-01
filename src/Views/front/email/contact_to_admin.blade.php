@extends('gp247-shop-front::email.layout')
@section('main')
<table class="inner-body" align="center" cellpadding="0" cellspacing="0">
  <tr>
     <td>
        <b>Name</b>: {{$name}}<br>
        <b>Email</b>: {{$email}}<br>
        <b>Phone</b>: {{$phone}}<br>
     </td>
  </tr>
  </table>
  <hr>
  <p style="text-align: center;">Content:<br>
  <table class="inner-body" align="center" cellpadding="0" cellspacing="0" border="0">
  <tr>
     <td>{{$content}}</td>
  </tr>
  </table>
@endsection
