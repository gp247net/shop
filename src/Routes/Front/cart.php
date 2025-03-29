<?php
/**
 * Route for cart
 */
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
        $prefixCartWishlist = config('gp247-config.shop.route.GP247_PREFIX_CART_WISHLIST') ?? 'wishlist';
        $prefixCartCompare = config('gp247-config.shop.route.GP247_PREFIX_CART_COMPARE') ?? 'compare';
        $prefixCartDefault = config('gp247-config.shop.route.GP247_PREFIX_CART_DEFAULT') ?? 'cart';

        //Wishlist
        $router->get($prefixCartWishlist.$suffix, $nameSpaceFrontCart.'\ShopCartController@wishlistProcessFront')
            ->name('cart.wishlist');

        //Compare
        $router->get($prefixCartCompare.$suffix, $nameSpaceFrontCart.'\ShopCartController@compareProcessFront')
            ->name('cart.compare');

        //Cart
        $router->get($prefixCartDefault.$suffix, $nameSpaceFrontCart.'\ShopCartController@getCartFront')
            ->name('cart');

        //Add to cart
        $router->post('/cart_add', $nameSpaceFrontCart.'\ShopCartController@addToCart')
            ->name('cart.add');

        //Add to cart ajax
        $router->post('/add_to_cart_ajax', $nameSpaceFrontCart.'\ShopCartController@addToCartAjax')
            ->name('cart.add_ajax');

        //Update cart
        $router->post('/update_to_cart', $nameSpaceFrontCart.'\ShopCartController@updateToCart')
            ->name('cart.update');

        //Remove item from cart
        $router->get('/{instance}/remove/{id}', $nameSpaceFrontCart.'\ShopCartController@removeItemProcessFront')
            ->name('cart.remove');

        //Clear cart
        $router->get('/clear_cart/{instance}', $nameSpaceFrontCart.'\ShopCartController@clearCartProcessFront')
            ->name('cart.clear');
    }
);
