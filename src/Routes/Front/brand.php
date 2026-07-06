<?php

use GP247\Shop\Controllers\ShopBrandController;

$langUrl = GP247_SEO_LANG ?'{lang?}/' : '';
$suffix = GP247_SUFFIX_URL;

$prefixBrand = config('gp247-config.shop.route.GP247_PREFIX_BRAND') ?? 'brand';
$brandController = gp247_namespace(ShopBrandController::class);

Route::group(
    [
        'prefix' => $langUrl.$prefixBrand,
    ],
    function ($router) use ($suffix, $brandController) {
        $router->get('/', $brandController.'@allBrandsProcessFront')
            ->name('brand.all');
        $router->get('/{alias}'.$suffix, $brandController.'@brandDetailProcessFront')
            ->name('brand.detail');
    }
);
