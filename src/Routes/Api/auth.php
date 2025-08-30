<?php
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => GP247_API_FRONT_PREFIX,
], function (){
    
    $listAbility = [
        config('gp247-config.api.auth.api_scope_user'),
        config('gp247-config.api.auth.api_scope_user_guest')
    ];

    if (file_exists(app_path('GP247/Shop/Api/Front/MemberAuthController.php'))) {
        $nameSpaceMemberAuth = 'App\GP247\Shop\Api\Front';
    } else {
        $nameSpaceMemberAuth = 'GP247\Shop\Api\Front';
    }
    //Login
    Route::post('login', $nameSpaceMemberAuth.'\MemberAuthController@login');

    Route::group([
        'middleware' => [
            'auth:customer-api', 
            'ability:'.implode(',', $listAbility)
        ]
    ], function () use($nameSpaceMemberAuth){
        //Logout
        Route::get('logout', $nameSpaceMemberAuth.'\MemberAuthController@logout');
        Route::get('info', $nameSpaceMemberAuth.'\MemberAuthController@getInfo');

        
        Route::group([
            'prefix' => 'member',
        ], function () use($nameSpaceMemberAuth) {
            Route::get('order/list', $nameSpaceMemberAuth.'\MemberAuthController@getOrderList');
            Route::get('order/detail/{id}', $nameSpaceMemberAuth.'\MemberAuthController@getOrderDetail');
        });
    });

});
