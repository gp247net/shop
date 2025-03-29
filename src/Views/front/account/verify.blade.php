@extends($GP247TemplatePath.'.layout')

@section('block_main')
<section class="page-section">
    <div class="container">
    <div class="row">
        <div class="col-lg-6 offset-lg-3">
            <div class="card">
                <div class="card-header">{{ gp247_language_render('customer.verify_email.title_header') }}</div>

                <div class="card-body">
                    @if (session('resent'))
                        <div class="alert alert-success" role="alert">
                            {{ gp247_language_render('customer.verify_email.msg_sent') }}
                        </div>
                    @endif

                    {{ gp247_language_render('customer.verify_email.msg_page_1') }}
                    <form class="d-inline" method="POST" action="">
                        @csrf
                        <button type="submit" class="btn btn-link p-0 m-0 align-baseline">{{ gp247_language_render('customer.verify_email.msg_page_2') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</section>
@endsection
