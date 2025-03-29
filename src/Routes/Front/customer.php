<?php
$suffix = GP247_SUFFIX_URL;

$prefixCustomer = config('gp247-config.shop.route.GP247_PREFIX_MEMBER') ?? 'customer';
if (file_exists(app_path('GP247/Shop/Controllers/ShopAccountController.php'))) {
    $nameSpaceFrontCustomer = 'App\GP247\Shop\Controllers';
} else {
    $nameSpaceFrontCustomer = 'GP247\Shop\Controllers';
}

Route::group(
    [
        'prefix' => $prefixCustomer,
        'middleware' => ['customer']
    ],
    function ($router) use ($suffix, $nameSpaceFrontCustomer) {
        $prefixCustomerOrderList    = config('gp247.cart.route.GP247_PREFIX_MEMBER_ORDER_LIST')??'order-list';
        $prefixCustomerOrderDetail  = config('gp247.cart.route.GP247_PREFIX_MEMBER_ORDER_DETAIL')??'order-detail';
        $prefixCustomerAddresList   = config('gp247.cart.route.GP247_PREFIX_MEMBER_ADDRESS_LIST')??'address-list';
        $prefixCustomerUpdateAddres = config('gp247.cart.route.GP247_PREFIX_MEMBER_UPDATE_ADDRESS')??'update-address';
        $prefixCustomerDeleteAddres = config('gp247.cart.route.GP247_PREFIX_MEMBER_DELETE_ADDRESS')??'delete-address';
        $prefixCustomerChangePwd    = config('gp247.cart.route.GP247_PREFIX_MEMBER_CHANGE_PWD')??'change-password';
        $prefixCustomerChangeInfo   = config('gp247.cart.route.GP247_PREFIX_MEMBER_CHANGE_INFO')??'change-infomation';


        $router->get('/', $nameSpaceFrontCustomer.'\ShopAccountController@index')
            ->name('customer.index');
        $router->get('/'.$prefixCustomerOrderList.$suffix, $nameSpaceFrontCustomer.'\ShopAccountController@orderList')
            ->name('customer.order_list');
        $router->get('/'.$prefixCustomerOrderDetail.'/{id}', $nameSpaceFrontCustomer.'\ShopAccountController@orderDetail')
            ->name('customer.order_detail');
        $router->get('/'.$prefixCustomerAddresList.$suffix, $nameSpaceFrontCustomer.'\ShopAccountController@addressList')
            ->name('customer.address_list');
        $router->get('/'.$prefixCustomerUpdateAddres.'/{id}', $nameSpaceFrontCustomer.'\ShopAccountController@updateAddress')
            ->name('customer.update_address');
        $router->post('/'.$prefixCustomerUpdateAddres.'/{id}', $nameSpaceFrontCustomer.'\ShopAccountController@postUpdateAddress')
            ->name('customer.post_update_address');
        $router->post('/'.$prefixCustomerDeleteAddres, $nameSpaceFrontCustomer.'\ShopAccountController@deleteAddress')
            ->name('customer.delete_address');
        $router->get('/'.$prefixCustomerChangePwd.$suffix, $nameSpaceFrontCustomer.'\ShopAccountController@changePassword')
            ->name('customer.change_password');
        $router->post('/change_password', $nameSpaceFrontCustomer.'\ShopAccountController@postChangePassword')
            ->name('customer.post_change_password');
        $router->get('/'.$prefixCustomerChangeInfo.$suffix, $nameSpaceFrontCustomer.'\ShopAccountController@changeInfomation')
            ->name('customer.change_infomation');
        $router->post('/change_infomation', $nameSpaceFrontCustomer.'\ShopAccountController@postChangeInfomation')
            ->name('customer.post_change_infomation');
        $router->get('/address_detail', $nameSpaceFrontCustomer.'\ShopAccountController@getAddress')
            ->name('customer.address_detail');

        // The Email Verification Notice
        $router->get('/email/verify', $nameSpaceFrontCustomer.'\ShopAccountController@verification')
            ->name('customer.verify');
        $router->post('/email/verify', $nameSpaceFrontCustomer.'\ShopAccountController@resendVerification')
            ->name('customer.verify_resend');

        $router->get('/email/verify/{id}/{token}', $nameSpaceFrontCustomer.'\ShopAccountController@verificationProcessData')
        ->name('customer.verify_process');
    }
);
