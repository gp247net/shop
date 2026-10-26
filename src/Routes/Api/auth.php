<?php

use GP247\Shop\Api\Front\MemberAuthController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => GP247_API_FRONT_PREFIX,
], function (){

    // Locked customers (status 0) cannot sign in any more, so the legacy
    // "user-guest" scope is no longer issued nor accepted (US-LW-customer-locked-login-block).
    $memberAuthController = gp247_namespace(MemberAuthController::class);
    //Login
    Route::post('login', $memberAuthController.'@login');

    Route::group([
        'middleware' => [
            'auth:customer-api',
            \GP247\Core\Api\Support\ApiAbilities::customer(),
            'api.customer.active',
        ]
    ], function () use($memberAuthController){
        //Logout — POST is the supported method; GET is kept for v1 clients (deprecated)
        Route::post('logout', $memberAuthController.'@logout');
        Route::get('logout', $memberAuthController.'@logout');
        Route::get('info', $memberAuthController.'@getInfo');


        Route::group([
            'prefix' => 'member',
        ], function () use($memberAuthController) {
            Route::get('order/list', $memberAuthController.'@getOrderList');
            Route::get('order/detail/{id}', $memberAuthController.'@getOrderDetail');
        });
    });

});
