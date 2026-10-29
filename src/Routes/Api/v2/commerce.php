<?php

use GP247\Shop\Api\V2\CartController;
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
});
