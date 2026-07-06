<?php

use GP247\Shop\Controllers\ShopProductController;

$langUrl = GP247_SEO_LANG ?'{lang?}/' : '';
$suffix = GP247_SUFFIX_URL;

$prefixProduct = config('gp247-config.shop.route.GP247_PREFIX_PRODUCT') ?? 'product';
$productController = gp247_namespace(ShopProductController::class);

Route::group(['prefix' => $langUrl.$prefixProduct], function ($router) use ($suffix, $productController) {
    $router->get('/', $productController.'@allProductsProcessFront')
        ->name('product.all');
    $router->get('/{alias}'.$suffix, $productController.'@productDetailProcessFront')
        ->name('product.detail');
});
