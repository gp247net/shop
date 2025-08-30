<?php
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => GP247_API_FRONT_PREFIX,
], function (){
    
    if (file_exists(app_path('GP247/Shop/Api/Front/FrontShop.php'))) {
        $nameSpaceFront = 'App\GP247\Shop\Api\Front';
    } else {
        $nameSpaceFront = 'GP247\Shop\Api\Front';
    }
    Route::group([
        'prefix' => 'product',
    ], function () use($nameSpaceFront) {
        Route::get('list', $nameSpaceFront.'\FrontShop@getProductList');
        Route::get('detail/{id}', $nameSpaceFront.'\FrontShop@getProductDetail');
    });
    Route::group([
        'prefix' => 'category',
    ], function () use($nameSpaceFront) {
        Route::get('list', $nameSpaceFront.'\FrontShop@getCategoryList');
        Route::get('detail/{id}', $nameSpaceFront.'\FrontShop@getCategoryDetail');
    });
    Route::group([
        'prefix' => 'brand',
    ], function () use($nameSpaceFront) {
        Route::get('list', $nameSpaceFront.'\FrontShop@getBrandList');
        Route::get('detail/{id}', $nameSpaceFront.'\FrontShop@getBrandDetail');
    });

});
