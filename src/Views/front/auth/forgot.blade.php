@php
/*
$layout_page = shop_auth
*/
@endphp

@extends($GP247TemplatePath.'.layout')

@section('block_main')
<section class="section section-sm section-first bg-default text-md-left">
    <div class="container">
    <div class="row">
        <div class="col-12 col-sm-12">
            <h2>{{ gp247_language_render('customer.password_forgot') }}</h2>

            <form class="form-horizontal" method="POST" action="{{ gp247_route_front('customer.password_email') }}" id="gp247-form-process">
                {{ csrf_field() }}
                <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                    <label for="email" class="col-md-12 control-label"><i class="fas fa-envelope"></i>
                        {{ gp247_language_render('customer.email') }}</label>
                    <div class="col-md-12">
                        <input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}"
                            required>
                        @if ($errors->has('email'))
                        <span class="help-block">
                            <strong>{{ $errors->first('email') }}</strong>
                        </span>
                        <br />
                        @endif
                        {!! $viewCaptcha ?? ''!!}
                        <button class="button button-secondary" type="submit" id="gp247-button-process">{{ gp247_language_render('action.submit') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
</section>

@endsection