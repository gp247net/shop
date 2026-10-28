<?php

use GP247\Core\Api\Support\ApiAbilities;
use GP247\Shop\Api\V2\AccountController;
use GP247\Shop\Api\V2\AddressController;
use GP247\Shop\Api\V2\CatalogController;
use GP247\Shop\Api\V2\MemberController;
use Illuminate\Support\Facades\Route;

// REST API v2 — public catalog + customer account (US-CMP-api-v2-contract) and the
// customer's own account, address book and sessions.
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
    $account = gp247_namespace(AccountController::class);
    $address = gp247_namespace(AddressController::class);
    Route::post('login', $member.'@login');
    Route::post('login/challenge', $member.'@challenge');
    Route::post('refresh', $member.'@refresh');
    Route::group(['middleware' => ['throttle:gp247-api-account']], function () use ($account) {
        Route::post('register', $account.'@register');
        Route::post('password/forgot', $account.'@forgotPassword');
        Route::post('password/reset', $account.'@resetPassword');
    });

    Route::group([
        'middleware' => ['auth:customer-api', ApiAbilities::customer(), 'api.customer.active'],
    ], function () use ($member, $account, $address) {
        Route::post('logout', $member.'@logout');
        Route::get('info', $member.'@profile');
        Route::get('member/order/list', $member.'@orderList');
        Route::get('member/order/detail/{id}', $member.'@orderDetail');
        Route::get('member/sessions', $member.'@sessions');
        Route::delete('member/sessions/{id}', $member.'@revokeSession');

        Route::get('member/profile', $account.'@profile');
        Route::put('member/profile', $account.'@updateProfile');
        Route::put('member/password', $account.'@changePassword');
        Route::post('member/email/verify', $account.'@resendVerification')->middleware('throttle:gp247-api-account');

        Route::get('member/address/list', $address.'@index');
        Route::post('member/address', $address.'@store');
        Route::put('member/address/{id}', $address.'@update');
        Route::delete('member/address/{id}', $address.'@destroy');
    });
});
