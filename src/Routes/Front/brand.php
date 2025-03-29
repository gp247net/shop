<?php
$langUrl = GP247_SEO_LANG ?'{lang?}/' : '';
$suffix = GP247_SUFFIX_URL;

$prefixBrand = config('gp247-config.shop.route.GP247_PREFIX_BRAND') ?? 'brand';
if (file_exists(app_path('GP247/Shop/Controllers/ShopBrandController.php'))) {
    $nameSpaceFrontBrand = 'App\GP247\Shop\Controllers';
} else {
    $nameSpaceFrontBrand = 'GP247\Shop\Controllers';
}

Route::group(
    [
        'prefix' => $langUrl.$prefixBrand,
    ],
    function ($router) use ($suffix, $nameSpaceFrontBrand) {
        $router->get('/', $nameSpaceFrontBrand.'\ShopBrandController@allBrandsProcessFront')
            ->name('brand.all');
        $router->get('/{alias}'.$suffix, $nameSpaceFrontBrand.'\ShopBrandController@brandDetailProcessFront')
            ->name('brand.detail');
    }
);
