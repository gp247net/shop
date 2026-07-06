<?php

use GP247\Shop\Controllers\Auth\ForgotPasswordController;
use GP247\Shop\Controllers\Auth\LoginController;
use GP247\Shop\Controllers\Auth\RegisterController;
use GP247\Shop\Controllers\Auth\ResetPasswordController;

$prefixCustomer = config('gp247-config.shop.route.GP247_PREFIX_MEMBER') ?? 'customer';
$langUrl = GP247_SEO_LANG ?'{lang?}/' : '';
$suffix = GP247_SUFFIX_URL;

//--Auth
$loginController = gp247_namespace(LoginController::class);
Route::group(
    [
        'prefix' => $langUrl.$prefixCustomer,
    ],
    function ($router) use ($suffix, $loginController) {
        $router->get('/login'.$suffix, $loginController.'@showLoginFormProcessFront')
            ->name('customer.login');
        $router->post('/login'.$suffix, $loginController.'@login')
            ->name('customer.postLogin');
        $router->any('/logout', $loginController.'@logout')
            ->name('customer.logout');
    }
);

$registerController = gp247_namespace(RegisterController::class);
Route::group(
    [
        'prefix' => $langUrl.$prefixCustomer,
    ],
    function ($router) use ($suffix, $registerController) {
        $router->get('/register'.$suffix, $registerController.'@showRegisterFormProcessFront')
            ->name('customer.register');
        $router->post('/register'.$suffix, $registerController.'@register')
            ->name('customer.postRegister');
    }
);

$forgotController = gp247_namespace(ForgotPasswordController::class);
Route::group(
    [
        'prefix' => $langUrl.$prefixCustomer,
    ],
    function ($router) use ($suffix, $forgotController) {
        $router->get('/forgot'.$suffix, $forgotController.'@showLinkRequestFormProcessFront')
            ->name('customer.forgot');
        $router->post('/password/email', $forgotController.'@sendResetLinkEmail')
            ->name('customer.password_email');
    }
);

$resetController = gp247_namespace(ResetPasswordController::class);
Route::group(
    [
        'prefix' => $langUrl.$prefixCustomer,
    ],
    function ($router) use ($resetController) {
        $router->get('/password/reset/{token}', $resetController.'@showResetFormProcessFront')
            ->name('customer.password_reset');
        $router->post('/password/reset', $resetController.'@reset')
            ->name('customer.password_request');
    }
);
//End Auth
