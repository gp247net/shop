<?php

use GP247\Shop\Api\Front\FrontShop;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => GP247_API_FRONT_PREFIX,
], function (){

    $frontShop = gp247_namespace(FrontShop::class);
    Route::group([
        'prefix' => 'product',
    ], function () use($frontShop) {
        Route::get('list', $frontShop.'@getProductList');
        Route::get('detail/{id}', $frontShop.'@getProductDetail');
    });
    Route::group([
        'prefix' => 'category',
    ], function () use($frontShop) {
        Route::get('list', $frontShop.'@getCategoryList');
        Route::get('detail/{id}', $frontShop.'@getCategoryDetail');
    });
    Route::group([
        'prefix' => 'brand',
    ], function () use($frontShop) {
        Route::get('list', $frontShop.'@getBrandList');
        Route::get('detail/{id}', $frontShop.'@getBrandDetail');
    });

});
