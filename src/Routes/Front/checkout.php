<?php

use GP247\Shop\Controllers\ShopCartController;

$langUrl = GP247_SEO_LANG ?'{lang?}/' : '';
$suffix = GP247_SUFFIX_URL;

$cartController = gp247_namespace(ShopCartController::class);
Route::group(
    [
        'prefix' => $langUrl,
    ],
    function ($router) use ($suffix, $cartController) {
        $prefixCartCheckout = config('gp247-config.shop.route.GP247_PREFIX_CART_CHECKOUT') ?? 'checkout';
        $prefixCartCheckoutConfirm = config('gp247-config.shop.route.GP247_PREFIX_CART_CHECKOUT_CONFIRM') ?? 'checkout-confirm';
        $prefixOrderSuccess = config('gp247-config.shop.route.GP247_PREFIX_ORDER_SUCCESS') ?? 'order-success';

        //Checkout prepare, from screen cart to checkout
        $router->post('/checkout-prepare', $cartController.'@prepareCheckout')
            ->name('checkout.prepare');

        //Checkout screen
        $router->get($prefixCartCheckout.$suffix, $cartController.'@getCheckoutFront')
            ->name('checkout');

        //Checkout process, from screen checkout to checkout confirm
        $router->post('/checkout-process', $cartController.'@processCheckout')
            ->name('checkout.process');

        //Checkout process, from screen checkout confirm to order
        $router->get($prefixCartCheckoutConfirm.$suffix, $cartController.'@getCheckoutConfirmFront')
            ->name('checkout.confirm');

        //Add order
        $router->post('/order-add', $cartController.'@addOrder')
            ->name('order.add');

        //Order success
        $router->get($prefixOrderSuccess.$suffix, $cartController.'@orderSuccessProcessFront')
            ->name('order.success');
    }
);
