<?php
/**
 * Route for cart
 */

use GP247\Shop\Controllers\ShopCartController;

$langUrl = GP247_SEO_LANG ?'{lang?}/' : '';
$suffix = GP247_SUFFIX_URL;

$cartController = gp247_namespace(ShopCartController::class);
Route::group(
    [
        'prefix' => $langUrl,
    ],
    function ($router) use ($suffix, $cartController) {
        $prefixCartWishlist = config('gp247-config.shop.route.GP247_PREFIX_CART_WISHLIST') ?? 'wishlist';
        $prefixCartCompare = config('gp247-config.shop.route.GP247_PREFIX_CART_COMPARE') ?? 'compare';
        $prefixCartDefault = config('gp247-config.shop.route.GP247_PREFIX_CART_DEFAULT') ?? 'cart';

        //Wishlist
        $router->get($prefixCartWishlist.$suffix, $cartController.'@wishlistProcessFront')
            ->name('cart.wishlist');

        //Compare
        $router->get($prefixCartCompare.$suffix, $cartController.'@compareProcessFront')
            ->name('cart.compare');

        //Cart
        $router->get($prefixCartDefault.$suffix, $cartController.'@getCartFront')
            ->name('cart');

        //Add to cart
        $router->post('/cart_add', $cartController.'@addToCart')
            ->name('cart.add');

        //Update cart
        $router->post('/update_to_cart', $cartController.'@updateToCart')
            ->name('cart.update');

        //Remove item from cart
        $router->get('/{instance}/remove/{id}', $cartController.'@removeItemProcessFront')
            ->name('cart.remove');

        //Clear cart
        $router->get('/clear_cart/{instance}', $cartController.'@clearCartProcessFront')
            ->name('cart.clear');
    }
);
