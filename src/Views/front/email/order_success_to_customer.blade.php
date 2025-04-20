@extends('gp247-shop-front::email.layout')

@section('main')
<div style="max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;">
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
        <div style="margin-bottom: 15px;">
            <p style="margin: 5px 0;"><strong>{{gp247_language_render('order.id')}}:</strong> <b>{{$orderID}}</b></p>
            <p style="margin: 5px 0;"><strong>{{gp247_language_render('order.full_name')}}:</strong> {{$toname}}</p>
            <p style="margin: 5px 0;"><strong>{{gp247_language_render('order.address')}}:</strong> {{$address}}</p>
            <p style="margin: 5px 0;"><strong>{{gp247_language_render('order.phone')}}:</strong> {{$phone}}</p>
            <p style="margin: 5px 0;"><strong>{{gp247_language_render('order.currency')}}:</strong> {{$currency}}</p>
            <p style="margin: 5px 0;"><strong>{{gp247_language_render('order.order_note')}}:</strong> {{$comment}}</p>
        </div>
    </div>

    <div style="background-color: #ffffff; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="color: #2c3e50; margin-top: 0; text-align: center;">{{gp247_language_render('order.order_details')}}</h3>
        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
            <tbody>
                <tr>
                        <td>{{ gp247_language_render('email.order.sort') }}</td>
                        <td>{{ gp247_language_render('email.order.sku') }}</td>
                        <td>{{ gp247_language_render('email.order.name') }}</td>
                        <td>{{ gp247_language_render('email.order.price') }}</td>
                        <td>{{ gp247_language_render('email.order.qty') }}</td>
                        <td>{{ gp247_language_render('email.order.total') }}</td>
                </tr>
                @foreach ($orderDetail as $key => $item)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $item['sku'] }}</td>
                        <td>{{ $item['name'] }} 
                            @if ($item['linkDownload'])
                                <a href="{{ $item['linkDownload'] }}">Download</a>
                            @endif
                        </td>
                        <td>{{ $item['price'] }}</td>
                        <td>{{ $item['qty'] }}</td>
                        <td  align="right">{{ $item['total'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 20px; border-top: 1px solid #dee2e6; padding-top: 15px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 5px 0;"><strong>{{gp247_language_render('order.subtotal')}}:</strong></td>
                    <td style="text-align: right;">{{$subtotal}}</td>
                </tr>
                <tr>
                    <td style="padding: 5px 0;"><strong>{{gp247_language_render('order.shipping')}}:</strong></td>
                    <td style="text-align: right;">{{$shipping}}</td>
                </tr>
                <tr>
                    <td style="padding: 5px 0;"><strong>{{gp247_language_render('order.discount')}}:</strong></td>
                    <td style="text-align: right;">{{$discount}}</td>
                </tr>
                <tr style="font-size: 1.2em;">
                    <td style="padding: 10px 0;"><strong>{{gp247_language_render('order.total')}}:</strong></td>
                    <td style="text-align: right;"><strong>{{$total}}</strong></td>
                </tr>
            </table>
        </div>
    </div>
</div>
@endsection
