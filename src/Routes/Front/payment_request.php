<?php

use GP247\Shop\Controllers\ShopPaymentRequestController;
use Illuminate\Support\Facades\Route;

/*
 * Public pay page of a core payment request (Payment Request slice S2).
 *
 * WHY no {lang?} prefix / suffix: the link is sent by email or chat and must keep
 * working whatever the site's SEO URL settings become. Both routes are throttled; the
 * token is the only credential, so its shape is checked before any lookup.
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-public-pay-page
 */
$payController = gp247_namespace(ShopPaymentRequestController::class);

Route::get('pay/{token}', $payController . '@show')
    ->where('token', '[A-Za-z0-9_-]{20,100}')
    ->middleware('throttle:30,1')
    ->name('payment_request.pay');

Route::post('pay/{token}', $payController . '@collect')
    ->where('token', '[A-Za-z0-9_-]{20,100}')
    ->middleware('throttle:30,1')
    ->name('payment_request.pay.collect');
