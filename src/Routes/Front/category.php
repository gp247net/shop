<?php
$langUrl = GP247_SEO_LANG ?'{lang?}/' : '';
$suffix = GP247_SUFFIX_URL;
$prefixCategory = config('gp247-config.shop.route.GP247_PREFIX_CATEGORY') ?? 'category';

if (file_exists(app_path('GP247/Shop/Controllers/ShopCategoryController.php'))) {
    $nameSpaceFrontCategory = 'App\GP247\Shop\Controllers';
} else {
    $nameSpaceFrontCategory = 'GP247\Shop\Controllers';
}

Route::group(
    [
        'prefix' => $langUrl.$prefixCategory,
    ],
    function ($router) use ($suffix, $nameSpaceFrontCategory) {
        $router->get('/', $nameSpaceFrontCategory.'\ShopCategoryController@allCategoriesProcessFront')
            ->name('category.all');
        $router->get('/{alias}'.$suffix, $nameSpaceFrontCategory.'\ShopCategoryController@categoryDetailProcessFront')
            ->name('category.detail');
    }
);
