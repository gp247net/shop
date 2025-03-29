<?php
$langUrl = GP247_SEO_LANG ?'{lang?}/' : '';
$suffix = GP247_SUFFIX_URL;

$prefixProduct = config('gp247-config.shop.route.GP247_PREFIX_PRODUCT') ?? 'product';
if (file_exists(app_path('GP247/Shop/Controllers/ShopProductController.php'))) {
    $nameSpaceFrontProduct = 'App\GP247\Shop\Controllers';
} else {
    $nameSpaceFrontProduct = 'GP247\Shop\Controllers';
}

Route::group(['prefix' => $langUrl.$prefixProduct], function ($router) use ($suffix, $nameSpaceFrontProduct) {
    $router->get('/', $nameSpaceFrontProduct.'\ShopProductController@allProductsProcessFront')
        ->name('product.all');
    $router->get('/{alias}'.$suffix, $nameSpaceFrontProduct.'\ShopProductController@productDetailProcessFront')
        ->name('product.detail');
});
