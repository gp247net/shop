<?php

use GP247\Core\Api\Support\ApiAbilities;
use GP247\Shop\Api\V2\CartController;
use GP247\Shop\Api\V2\CheckoutController;
use GP247\Shop\Api\V2\PaymentStatusController;
use Illuminate\Support\Facades\Route;

// REST API v2 — cart (and, later, checkout) of the shopper (Unit api-commerce). Every route
// runs behind CheckoutSessionBridge: the owner's cart is loaded into a session that lives
// only for the request (ADR api-commerce_request-scoped-checkout-session).
Route::group([
    'prefix'     => GP247_API_V2_FRONT_PREFIX,
    'middleware' => ['throttle:gp247-api-cart', 'api.commerce.session'],
], function () {
    $cart = gp247_namespace(CartController::class);
    Route::get('cart', $cart . '@show');
    Route::post('cart/items', $cart . '@addItem');
    Route::patch('cart/items/{rowId}', $cart . '@updateItem');
    Route::delete('cart/items/{rowId}', $cart . '@removeItem');

    $checkout = gp247_namespace(CheckoutController::class);
    Route::post('cart/promotions/{pluginKey}', $checkout . '@applyPromotion');
    Route::delete('cart/promotions/{pluginKey}', $checkout . '@removePromotion');
    Route::get('checkout', $checkout . '@show');
    Route::put('checkout/address', $checkout . '@address');
    Route::get('checkout/shipping-methods', $checkout . '@shippingMethods');
    Route::put('checkout/shipping-method', $checkout . '@selectShipping');
    Route::get('checkout/payment-methods', $checkout . '@paymentMethods');
    Route::put('checkout/payment-method', $checkout . '@selectPayment');
    Route::get('checkout/totals', $checkout . '@totals');
    // Behind the "ordering through the API" switch (core API switches) and its own limit.
    Route::post('checkout/place', $checkout . '@place')->middleware('throttle:gp247-api-checkout-place');
});

// Payment status of an order placed through the API: the signed-in customer's own order, or
// the one order a guest's X-Order-Access token opens. No cart session needed.
Route::group([
    'prefix'     => GP247_API_V2_FRONT_PREFIX,
    'middleware' => ['throttle:gp247-api-cart'],
], function () {
    $status = gp247_namespace(PaymentStatusController::class);
    Route::get('orders/{id}/payment', $status . '@guest');
    Route::get('member/orders/{id}/payment', $status . '@member')
        ->middleware(['auth:customer-api', ApiAbilities::customer(), 'api.customer.active']);
    // Cancel an unpaid order placed through the API (stock back) — same owners as the status.
    Route::post('orders/{id}/cancel', $status . '@guestCancel');
    Route::post('member/orders/{id}/cancel', $status . '@memberCancel')
        ->middleware(['auth:customer-api', ApiAbilities::customer(), 'api.customer.active']);
});
