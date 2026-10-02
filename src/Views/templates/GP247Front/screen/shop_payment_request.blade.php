{{--
    Public pay page of a payment request (ShopPaymentRequestController).
    Shows only what paying needs — store, description, amount, payer name, deadline —
    never the payer's email/phone or internal notes (NFR-SEC-payment-request-public-link).

    A whole document of its own, styled inline: it does not extend a template layout nor
    load any template CSS/JS, because a site on another template may never have published
    the default template's assets. It renders the same on every template.

    To customise it, create screen/shop_payment_request.blade.php in your own template
    (app/GP247/Templates/<YourTemplate>/screen/) — copying this file is a good start. That
    file wins over this one; nothing in the package or in other templates changes.

    Vars: $paymentRequest, $token, $state (payable|settled|cancelled|expired|no_gateway|unavailable),
          $gateways (key => label, only when payable), $outstanding, $total, $title.

    @aidlc-unit payment-request
    @aidlc-story US-payment-request-public-pay-page
--}}
@php
    $storeName = gp247_store_info('title');
    $storeLogo = gp247_store_info('logo');
    $rtl = function_exists('gp247front_is_rtl') && gp247front_is_rtl();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $title }}{{ $storeName ? ' — ' . $storeName : '' }}</title>
    <style>
        :root {
            --pr-bg: #f4f6fa; --pr-card: #ffffff; --pr-text: #0f172a; --pr-muted: #475569;
            --pr-border: #e2e8f0; --pr-accent: #2563eb; --pr-accent-text: #ffffff; --pr-accent-soft: #eff6ff;
            --pr-ok-bg: #ecfdf5; --pr-ok-border: #a7f3d0; --pr-ok-text: #047857;
            --pr-err-bg: #fef2f2; --pr-err-border: #fecaca; --pr-err-text: #b91c1c;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --pr-bg: #0b1120; --pr-card: #111827; --pr-text: #f1f5f9; --pr-muted: #94a3b8;
                --pr-border: #1f2937; --pr-accent: #3b82f6; --pr-accent-soft: #172554;
                --pr-ok-bg: #022c22; --pr-ok-border: #065f46; --pr-ok-text: #6ee7b7;
                --pr-err-bg: #2a0a0a; --pr-err-border: #7f1d1d; --pr-err-text: #fca5a5;
            }
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; background: var(--pr-bg); color: var(--pr-text);
            font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .pr-wrap { max-width: 30rem; margin: 0 auto; padding: 2.5rem 1rem; }
        .pr-store { display: flex; align-items: center; gap: .75rem; margin-bottom: 1.5rem; }
        .pr-store img { max-height: 2.5rem; max-width: 10rem; }
        .pr-store span { font-weight: 600; }
        h1 { font-size: 1.375rem; margin: 0 0 1rem; }
        .pr-card { background: var(--pr-card); border: 1px solid var(--pr-border); border-radius: .75rem; padding: 1.5rem; }
        .pr-desc { font-size: 1.0625rem; font-weight: 500; margin: 0 0 .75rem; }
        .pr-row { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; margin-top: .5rem; }
        .pr-muted { color: var(--pr-muted); font-size: .875rem; }
        .pr-small { color: var(--pr-muted); font-size: .8125rem; }
        .pr-amount { font-size: 1.75rem; font-weight: 700; }
        .pr-note { border: 1px solid var(--pr-border); border-radius: .5rem; padding: .75rem 1rem; margin: 0 0 1rem; font-size: .875rem; }
        .pr-note.is-ok { background: var(--pr-ok-bg); border-color: var(--pr-ok-border); color: var(--pr-ok-text); }
        .pr-note.is-error { background: var(--pr-err-bg); border-color: var(--pr-err-border); color: var(--pr-err-text); }
        .pr-note.is-info { background: var(--pr-card); color: var(--pr-muted); }
        .pr-form { margin-top: 1.5rem; }
        .pr-form p { margin: 0 0 .5rem; font-weight: 500; color: var(--pr-muted); font-size: .875rem; }
        .pr-option { display: flex; align-items: center; gap: .75rem; margin-bottom: .5rem; padding: .75rem 1rem; cursor: pointer;
            background: var(--pr-card); border: 1px solid var(--pr-border); border-radius: .5rem; font-weight: 500; }
        .pr-option:has(input:checked) { border-color: var(--pr-accent); background: var(--pr-accent-soft); }
        .pr-option input { accent-color: var(--pr-accent); margin: 0; }
        .pr-submit { display: block; width: 100%; margin-top: 1rem; padding: .8rem 1rem; border: 0; border-radius: .5rem; cursor: pointer;
            background: var(--pr-accent); color: var(--pr-accent-text); font: inherit; font-weight: 600; }
        .pr-submit:hover { filter: brightness(1.08); }
        .pr-submit:focus-visible, .pr-option:focus-within { outline: 2px solid var(--pr-accent); outline-offset: 2px; }
        .pr-state { margin: 1.5rem 0 0; }
    </style>
</head>
<body>
<main class="pr-wrap" data-testid="payment-request-pay-page">
    @if (!empty($storeLogo) || !empty($storeName))
        <div class="pr-store">
            @if (!empty($storeLogo))
                <img src="{{ gp247_file($storeLogo) }}" alt="{{ $storeName }}">
            @else
                <span>{{ $storeName }}</span>
            @endif
        </div>
    @endif

    <h1>{{ $title }}</h1>

    @if (session('error'))
        <div class="pr-note is-error" role="alert" data-testid="payment-request-pay-error">{{ session('error') }}</div>
    @endif
    @if (session('success'))
        <div class="pr-note is-ok" role="status" data-testid="payment-request-pay-notice">{{ session('success') }}</div>
    @endif

    <section class="pr-card" data-testid="payment-request-pay-summary">
        @if (!empty($paymentRequest->description))
            <p class="pr-desc">{{ $paymentRequest->description }}</p>
        @endif
        @if (!empty($paymentRequest->party_name))
            <div class="pr-muted">{{ gp247_language_render('front.payment_request.payer') }}: <strong>{{ $paymentRequest->party_name }}</strong></div>
        @endif
        <div class="pr-row">
            <span class="pr-muted">{{ gp247_language_render('front.payment_request.amount_due') }}</span>
            <span class="pr-amount" data-testid="payment-request-pay-amount">{{ $outstanding }}</span>
        </div>
        @if ($outstanding !== $total)
            <div class="pr-row pr-small">
                <span>{{ gp247_language_render('front.payment_request.amount_total') }}</span>
                <span>{{ $total }}</span>
            </div>
        @endif
        @if ($paymentRequest->expires_at !== null && $state === 'payable')
            <div class="pr-small" style="margin-top: .5rem">
                {{ gp247_language_render('front.payment_request.expires_at') }}: {{ $paymentRequest->expires_at->format('Y-m-d H:i') }}
            </div>
        @endif
    </section>

    @if ($state === 'payable')
        <form method="post" action="{{ route('payment_request.pay.collect', ['token' => $token]) }}" class="pr-form">
            @csrf
            <p>{{ gp247_language_render('front.payment_request.choose_gateway') }}</p>
            @foreach ($gateways as $key => $label)
                <label class="pr-option">
                    <input type="radio" name="gateway" value="{{ $key }}" data-testid="payment-request-pay-gateway" @checked($loop->first) required>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
            <button type="submit" class="pr-submit" data-testid="payment-request-pay-submit">
                {{ gp247_language_render('front.payment_request.pay_now') }}
            </button>
        </form>
    @else
        <div class="pr-note pr-state {{ $state === 'settled' ? 'is-ok' : 'is-info' }}" role="status"
            data-testid="payment-request-pay-state" data-state="{{ $state }}">
            {{ gp247_language_render('front.payment_request.state_' . $state) }}
        </div>
    @endif
</main>
</body>
</html>
