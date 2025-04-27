<?php
$prefixCustomer = config('gp247-config.shop.route.GP247_PREFIX_MEMBER') ?? 'customer';
$langUrl = GP247_SEO_LANG ?'{lang?}/' : '';
$suffix = GP247_SUFFIX_URL;
//Process namespace
if (file_exists(app_path('GP247/Shop/Controllers/Auth/LoginController.php'))) {
    $nameSpaceFrontLogin = 'App\GP247\Shop\Controllers';
} else {
    $nameSpaceFrontLogin = 'GP247\Shop\Controllers';
}
if (file_exists(app_path('GP247/Shop/Controllers/Auth/RegisterController.php'))) {
    $nameSpaceFrontRegister = 'App\GP247\Shop\Controllers';
} else {
    $nameSpaceFrontRegister = 'GP247\Shop\Controllers';
}
if (file_exists(app_path('GP247/Shop/Controllers/Auth/ForgotPasswordController.php'))) {
    $nameSpaceFrontForgot = 'App\GP247\Shop\Controllers';
} else {
    $nameSpaceFrontForgot = 'GP247\Shop\Controllers';
}
if (file_exists(app_path('GP247/Shop/Controllers/Auth/ResetPasswordController.php'))) {
    $nameSpaceFrontReset = 'App\GP247\Shop\Controllers';
} else {
    $nameSpaceFrontReset = 'GP247\Shop\Controllers';
}

//--Auth
Route::group(
    [
        'namespace' => $nameSpaceFrontLogin.'\Auth',
        'prefix' => $langUrl.$prefixCustomer,
    ],
    function ($router) use ($suffix) {
        $router->get('/login'.$suffix, 'LoginController@showLoginFormProcessFront')
            ->name('customer.login');
        $router->post('/login'.$suffix, 'LoginController@login')
            ->name('customer.postLogin');
        $router->any('/logout', 'LoginController@logout')
            ->name('customer.logout');
    }
);

Route::group(
    [
        'namespace' => $nameSpaceFrontRegister.'\Auth',
        'prefix' => $langUrl.$prefixCustomer,
    ],
    function ($router) use ($suffix) {
        $router->get('/register'.$suffix, 'RegisterController@showRegisterFormProcessFront')
            ->name('customer.register');
        $router->post('/register'.$suffix, 'RegisterController@register')
            ->name('customer.postRegister');
    }
);

Route::group(
    [
        'namespace' => $nameSpaceFrontForgot.'\Auth',
        'prefix' => $langUrl.$prefixCustomer,
    ],
    function ($router) use ($suffix) {
        $router->get('/forgot'.$suffix, 'ForgotPasswordController@showLinkRequestFormProcessFront')
            ->name('customer.forgot');
        $router->post('/password/email', 'ForgotPasswordController@sendResetLinkEmail')
            ->name('customer.password_email');
    }
);

Route::group(
    [
        'namespace' => $nameSpaceFrontReset.'\Auth',
        'prefix' => $langUrl.$prefixCustomer,
    ],
    function ($router) {
        $router->get('/password/reset/{token}', 'ResetPasswordController@showResetFormProcessFront')
            ->name('customer.password_reset');
        $router->post('/password/reset', 'ResetPasswordController@reset')
            ->name('customer.password_request');
    }
);
//End Auth
