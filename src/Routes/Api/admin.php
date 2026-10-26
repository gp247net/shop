<?php

use GP247\Shop\Api\Admin\AdminBrandController;
use GP247\Shop\Api\Admin\AdminCategoryController;
use GP247\Shop\Api\Admin\AdminCustomerController;
use GP247\Shop\Api\Admin\AdminOrderController;
use GP247\Shop\Api\Admin\AdminProductController;
use GP247\Shop\Api\Admin\AdminSupplierController;
use Illuminate\Support\Facades\Route;


// Each endpoint declares the admin screen whose RBAC grant opens it
// (api.admin:<admin route name>, US-RBAC-api-route-permission).
Route::group([
    'middleware' => [
        'auth:admin-api',
        \GP247\Core\Api\Support\ApiAbilities::admin(),
    ],
    'prefix' => GP247_API_CORE_PREFIX,
], function (){
    // Customer
        $customerController = gp247_namespace(AdminCustomerController::class);
        Route::group([
            'prefix' => 'customer',
            'middleware' => 'api.admin:admin_customer.index',
        ], function () use($customerController) {
            Route::get('list', $customerController.'@getCustomerList');
            Route::get('detail/{id}', $customerController.'@getCustomerDetail');
        });

    // Order
        $orderController = gp247_namespace(AdminOrderController::class);
        Route::group([
            'prefix' => 'order',
        ], function () use($orderController) {
            Route::get('list', $orderController.'@getOrderList')->middleware('api.admin:admin_order.index');
            Route::get('detail/{id}', $orderController.'@getOrderDetail')->middleware('api.admin:admin_order.detail');
        });

    // Category
        $categoryController = gp247_namespace(AdminCategoryController::class);
        Route::group([
            'prefix' => 'category',
            'middleware' => 'api.admin:admin_category.index',
        ], function () use($categoryController) {
            Route::get('list', $categoryController.'@getCategoryList');
            Route::get('detail/{id}', $categoryController.'@getCategoryDetail');
        });

    // Product
        $productController = gp247_namespace(AdminProductController::class);
        Route::group([
            'prefix' => 'product',
            'middleware' => 'api.admin:admin_product.index',
        ], function () use($productController) {
            Route::get('list', $productController.'@getProductList');
            Route::get('detail/{id}', $productController.'@getProductDetail');
        });

    // Brand
        $brandController = gp247_namespace(AdminBrandController::class);
        Route::group([
            'prefix' => 'brand',
            'middleware' => 'api.admin:admin_brand.index',
        ], function () use($brandController) {
            Route::get('list', $brandController.'@getBrandList');
            Route::get('detail/{id}', $brandController.'@getBrandDetail');
        });

    // Supplier
        $supplierController = gp247_namespace(AdminSupplierController::class);
        Route::group([
            'prefix' => 'supplier',
            'middleware' => 'api.admin:admin_supplier.index',
        ], function () use($supplierController) {
            Route::get('list', $supplierController.'@getSupplierList');
            Route::get('detail/{id}', $supplierController.'@getSupplierDetail');
        });

});
