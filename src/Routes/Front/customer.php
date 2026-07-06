<?php

use GP247\Shop\Controllers\ShopAccountController;

$suffix = GP247_SUFFIX_URL;

$prefixCustomer = config('gp247-config.shop.route.GP247_PREFIX_MEMBER') ?? 'customer';
$customerController = gp247_namespace(ShopAccountController::class);

Route::group(
    [
        'prefix' => $prefixCustomer,
        'middleware' => ['customer']
    ],
    function ($router) use ($suffix, $customerController) {
        $prefixCustomerOrderList    = config('gp247.cart.route.GP247_PREFIX_MEMBER_ORDER_LIST')??'order-list';
        $prefixCustomerOrderDetail  = config('gp247.cart.route.GP247_PREFIX_MEMBER_ORDER_DETAIL')??'order-detail';
        $prefixCustomerAddresList   = config('gp247.cart.route.GP247_PREFIX_MEMBER_ADDRESS_LIST')??'address-list';
        $prefixCustomerUpdateAddres = config('gp247.cart.route.GP247_PREFIX_MEMBER_UPDATE_ADDRESS')??'update-address';
        $prefixCustomerDeleteAddres = config('gp247.cart.route.GP247_PREFIX_MEMBER_DELETE_ADDRESS')??'delete-address';
        $prefixCustomerChangePwd    = config('gp247.cart.route.GP247_PREFIX_MEMBER_CHANGE_PWD')??'change-password';
        $prefixCustomerChangeInfo   = config('gp247.cart.route.GP247_PREFIX_MEMBER_CHANGE_INFO')??'change-infomation';


        $router->get('/', $customerController.'@index')
            ->name('customer.index');
        $router->get('/'.$prefixCustomerOrderList.$suffix, $customerController.'@orderList')
            ->name('customer.order_list');
        $router->get('/'.$prefixCustomerOrderDetail.'/{id}', $customerController.'@orderDetail')
            ->name('customer.order_detail');
        $router->get('/'.$prefixCustomerAddresList.$suffix, $customerController.'@addressList')
            ->name('customer.address_list');
        $router->get('/'.$prefixCustomerUpdateAddres.'/{id}', $customerController.'@updateAddress')
            ->name('customer.update_address');
        $router->post('/'.$prefixCustomerUpdateAddres.'/{id}', $customerController.'@postUpdateAddress')
            ->name('customer.post_update_address');
        $router->post('/'.$prefixCustomerDeleteAddres, $customerController.'@deleteAddress')
            ->name('customer.delete_address');
        $router->get('/'.$prefixCustomerChangePwd.$suffix, $customerController.'@changePassword')
            ->name('customer.change_password');
        $router->post('/change_password', $customerController.'@postChangePassword')
            ->name('customer.post_change_password');
        $router->get('/'.$prefixCustomerChangeInfo.$suffix, $customerController.'@changeInfomation')
            ->name('customer.change_infomation');
        $router->post('/change_infomation', $customerController.'@postChangeInfomation')
            ->name('customer.post_change_infomation');
        $router->get('/address_detail', $customerController.'@getAddress')
            ->name('customer.address_detail');

        // The Email Verification Notice
        $router->get('/email/verify', $customerController.'@verification')
            ->name('customer.verify');
        $router->post('/email/verify', $customerController.'@resendVerification')
            ->name('customer.verify_resend');

        $router->get('/email/verify/{id}/{token}', $customerController.'@verificationProcessData')
        ->name('customer.verify_process');
    }
);
