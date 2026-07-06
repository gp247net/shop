<?php

use GP247\Shop\Controllers\ShopCategoryController;

$langUrl = GP247_SEO_LANG ?'{lang?}/' : '';
$suffix = GP247_SUFFIX_URL;
$prefixCategory = config('gp247-config.shop.route.GP247_PREFIX_CATEGORY') ?? 'category';

$categoryController = gp247_namespace(ShopCategoryController::class);

Route::group(
    [
        'prefix' => $langUrl.$prefixCategory,
    ],
    function ($router) use ($suffix, $categoryController) {
        $router->get('/', $categoryController.'@allCategoriesProcessFront')
            ->name('category.all');
        $router->get('/{alias}'.$suffix, $categoryController.'@categoryDetailProcessFront')
            ->name('category.detail');
    }
);
