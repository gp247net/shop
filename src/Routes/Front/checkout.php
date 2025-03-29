<?php
$langUrl = GP247_SEO_LANG ?'{lang?}/' : '';
$suffix = GP247_SUFFIX_URL;

if (file_exists(app_path('GP247/Shop/Controllers/ShopCartController.php'))) {
    $nameSpaceFrontCart = 'App\GP247\Shop\Controllers';
} else {
    $nameSpaceFrontCart = 'GP247\Shop\Controllers';
}
Route::group(
    [
        'prefix' => $langUrl,
    ],
    function ($router) use ($suffix, $nameSpaceFrontCart) {
        $prefixCartCheckout = config('gp247-config.shop.route.GP247_PREFIX_CART_CHECKOUT') ?? 'checkout';
        $prefixCartCheckoutConfirm = config('gp247-config.shop.route.GP247_PREFIX_CART_CHECKOUT_CONFIRM') ?? 'checkout-confirm';
        $prefixOrderSuccess = config('gp247-config.shop.route.GP247_PREFIX_ORDER_SUCCESS') ?? 'order-success';
        
        //Checkout prepare, from screen cart to checkout
        $router->post('/checkout-prepare', $nameSpaceFrontCart.'\ShopCartController@prepareCheckout')
            ->name('checkout.prepare');

        //Checkout screen
        $router->get($prefixCartCheckout.$suffix, $nameSpaceFrontCart.'\ShopCartController@getCheckoutFront')
            ->name('checkout');

        //Checkout process, from screen checkout to checkout confirm
        $router->post('/checkout-process', $nameSpaceFrontCart.'\ShopCartController@processCheckout')
            ->name('checkout.process');

        //Checkout process, from screen checkout confirm to order
        $router->get($prefixCartCheckoutConfirm.$suffix, $nameSpaceFrontCart.'\ShopCartController@getCheckoutConfirmFront')
            ->name('checkout.confirm');

        //Add order
        $router->post('/order-add', $nameSpaceFrontCart.'\ShopCartController@addOrder')
            ->name('order.add');

        //Order success
        $router->get($prefixOrderSuccess.$suffix, $nameSpaceFrontCart.'\ShopCartController@orderSuccessProcessFront')
            ->name('order.success');
    }
);
