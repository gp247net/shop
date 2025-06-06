@php
/*
$layout_page = shop_profile
**Variables:**
- $customer
*/ 
@endphp

@php
    $view = gp247_shop_process_view($GP247TemplatePath, 'account.layout');
@endphp
@extends($view)

@section('block_main_profile')
    <h6 class="title-store">{{ $title }}</h6>

            <form method="POST" action="{{ gp247_route_front('customer.post_change_password') }}">
                @csrf

                <div class="form-group row {{ Session::has('password_old_error') ? ' has-error' : '' }}">
                    <label for="password"
                        class="col-md-4 col-form-label text-md-right">{{ gp247_language_render('customer.password_old') }}</label>

                    <div class="col-md-6">
                        <input id="password" type="password" class="form-control" name="password_old" required>

                        @if(Session::has('password_old_error'))
                        <span class="help-block">{{ Session::get('password_old_error') }}</span>
                        @endif

                    </div>
                </div>

                <div class="form-group row {{ $errors->has('password') ? ' has-error' : '' }}">
                    <label for="password"
                        class="col-md-4 col-form-label text-md-right">{{ gp247_language_render('customer.password_new') }}</label>

                    <div class="col-md-6">
                        <input id="password" type="password" class="form-control" name="password" required>

                        @if($errors->has('password'))
                        <span class="help-block">{{ $errors->first('password') }}</span>
                        @endif

                    </div>
                </div>

                <div class="form-group row">
                    <label for="password-confirm"
                        class="col-md-4 col-form-label text-md-right">{{ gp247_language_render('customer.password_confirm') }}</label>

                    <div class="col-md-6">
                        <input id="password-confirm" type="password" class="form-control"
                            name="password_confirmation" required>
                    </div>
                </div>

                <div class="form-group row mb-0">
                    <div class="col-md-6 offset-md-4">
                        <button class="button button-secondary" type="submit" id="">{{ gp247_language_render('customer.update_infomation') }}</button>
                    </div>
                </div>
            </form>
@endsection