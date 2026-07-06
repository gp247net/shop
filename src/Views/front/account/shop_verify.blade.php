{{--
    Email verification notice. Tailwind port of vendor's account/shop_verify.blade.php.
    Extends the top-level layout directly (not account.shop_layout) — matches vendor,
    which overrides block_main directly instead of going through the sidebar shell.
    The resend form keeps a blank action="" (self-submit) exactly as vendor does.

    Variables (unchanged from vendor):
    - $customer

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@extends($GP247TemplatePath.'.layout')

@section('block_main_content_center')
<div class="lg:col-span-12 w-full card p-8 max-w-xl mx-auto text-center">
    @if (session('resent'))
        <div class="rounded-lg bg-green-50 text-green-700 text-sm px-4 py-3 mb-4">
            {{ gp247_language_render('customer.verify_email.msg_sent') }}
        </div>
    @endif

    <p class="text-ink-600 mb-4">
        {{ gp247_language_render('customer.verify_email.msg_page_1') }}
    </p>

    <form method="POST" action="">
        @csrf
        <button type="submit" class="btn-primary">{{ gp247_language_render('customer.verify_email.msg_page_2') }}</button>
    </form>
</div>
@endsection
