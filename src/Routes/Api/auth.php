<?php

use GP247\Shop\Api\Front\MemberAuthController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => GP247_API_FRONT_PREFIX,
], function (){

    $listAbility = [
        config('gp247-config.api.auth.api_scope_user'),
        config('gp247-config.api.auth.api_scope_user_guest')
    ];

    $memberAuthController = gp247_namespace(MemberAuthController::class);
    //Login
    Route::post('login', $memberAuthController.'@login');

    Route::group([
        'middleware' => [
            'auth:customer-api',
            'ability:'.implode(',', $listAbility)
        ]
    ], function () use($memberAuthController){
        //Logout
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
