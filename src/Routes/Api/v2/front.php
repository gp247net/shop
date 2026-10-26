<?php

use GP247\Core\Api\Support\ApiAbilities;
use GP247\Shop\Api\V2\CatalogController;
use GP247\Shop\Api\V2\MemberController;
use Illuminate\Support\Facades\Route;

// REST API v2 — public catalog + customer account (US-CMP-api-v2-contract).
Route::group([
    'prefix' => GP247_API_V2_FRONT_PREFIX,
], function () {
    $catalog = gp247_namespace(CatalogController::class);
    Route::get('product/list', $catalog.'@productList');
    Route::get('product/detail/{id}', $catalog.'@productDetail');
    Route::get('category/list', $catalog.'@categoryList');
    Route::get('category/detail/{id}', $catalog.'@categoryDetail');
    Route::get('brand/list', $catalog.'@brandList');
    Route::get('brand/detail/{id}', $catalog.'@brandDetail');

    $member = gp247_namespace(MemberController::class);
    Route::post('login', $member.'@login');
    Route::group([
        'middleware' => ['auth:customer-api', ApiAbilities::customer(), 'api.customer.active'],
    ], function () use ($member) {
        Route::post('logout', $member.'@logout');
        Route::get('info', $member.'@profile');
        Route::get('member/order/list', $member.'@orderList');
        Route::get('member/order/detail/{id}', $member.'@orderDetail');
    });
});
