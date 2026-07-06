<?php

use GP247\Shop\Api\Admin\AdminBrandController;
use GP247\Shop\Api\Admin\AdminCategoryController;
use GP247\Shop\Api\Admin\AdminCustomerController;
use GP247\Shop\Api\Admin\AdminOrderController;
use GP247\Shop\Api\Admin\AdminProductController;
use GP247\Shop\Api\Admin\AdminSupplierController;
use Illuminate\Support\Facades\Route;

$listAbility = [
    config('gp247-config.api.auth.api_scope_admin'),
    config('gp247-config.api.auth.api_scope_admin_supper')
];


Route::group([
    'middleware' => [
        'auth:admin-api',
        'ability:'.implode(',', $listAbility)
    ],
    'prefix' => GP247_API_CORE_PREFIX,
], function (){
    // Customer
        $customerController = gp247_namespace(AdminCustomerController::class);
        Route::group([
            'prefix' => 'customer',
        ], function () use($customerController) {
            Route::get('list', $customerController.'@getCustomerList');
            Route::get('detail/{id}', $customerController.'@getCustomerDetail');
        });

    // Order
        $orderController = gp247_namespace(AdminOrderController::class);
        Route::group([
            'prefix' => 'order',
        ], function () use($orderController) {
            Route::get('list', $orderController.'@getOrderList');
            Route::get('detail/{id}', $orderController.'@getOrderDetail');
        });

    // Category
        $categoryController = gp247_namespace(AdminCategoryController::class);
        Route::group([
            'prefix' => 'category',
        ], function () use($categoryController) {
            Route::get('list', $categoryController.'@getCategoryList');
            Route::get('detail/{id}', $categoryController.'@getCategoryDetail');
        });

    // Product
        $productController = gp247_namespace(AdminProductController::class);
        Route::group([
            'prefix' => 'product',
        ], function () use($productController) {
            Route::get('list', $productController.'@getProductList');
            Route::get('detail/{id}', $productController.'@getProductDetail');
        });

    // Brand
        $brandController = gp247_namespace(AdminBrandController::class);
        Route::group([
            'prefix' => 'brand',
        ], function () use($brandController) {
            Route::get('list', $brandController.'@getBrandList');
            Route::get('detail/{id}', $brandController.'@getBrandDetail');
        });

    // Supplier
        $supplierController = gp247_namespace(AdminSupplierController::class);
        Route::group([
            'prefix' => 'supplier',
        ], function () use($supplierController) {
            Route::get('list', $supplierController.'@getSupplierList');
            Route::get('detail/{id}', $supplierController.'@getSupplierDetail');
        });

});
