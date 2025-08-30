<?php
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
        if (file_exists(app_path('GP247/Shop/Api/Admin/AdminCustomerController.php'))) {
            $nameSpaceAdminCustomer = 'App\GP247\Shop\Api\Admin';
        } else {
            $nameSpaceAdminCustomer = 'GP247\Shop\Api\Admin';
        }
        Route::group([
            'prefix' => 'customer',
        ], function () use($nameSpaceAdminCustomer) {
            Route::get('list', $nameSpaceAdminCustomer.'\AdminCustomerController@getCustomerList');
            Route::get('detail/{id}', $nameSpaceAdminCustomer.'\AdminCustomerController@getCustomerDetail');
        });

    // Order
        if (file_exists(app_path('GP247/Shop/Api/Admin/AdminOrderController.php'))) {
            $nameSpaceAdminOrder = 'App\GP247\Shop\Api\Admin';
        } else {
            $nameSpaceAdminOrder = 'GP247\Shop\Api\Admin';
        }
        Route::group([
            'prefix' => 'order',
        ], function () use($nameSpaceAdminOrder) {
            Route::get('list', $nameSpaceAdminOrder.'\AdminOrderController@getOrderList');
            Route::get('detail/{id}', $nameSpaceAdminOrder.'\AdminOrderController@getOrderDetail');
        });

    // Category
        if (file_exists(app_path('GP247/Shop/Api/Admin/AdminCategoryController.php'))) {
            $nameSpaceAdminCategory = 'App\GP247\Shop\Api\Admin';
        } else {
            $nameSpaceAdminCategory = 'GP247\Shop\Api\Admin';
        }
        Route::group([
            'prefix' => 'category',
        ], function () use($nameSpaceAdminCategory) {
            Route::get('list', $nameSpaceAdminCategory.'\AdminCategoryController@getCategoryList');
            Route::get('detail/{id}', $nameSpaceAdminCategory.'\AdminCategoryController@getCategoryDetail');
        });

    // Product
        if (file_exists(app_path('GP247/Shop/Api/Admin/AdminProductController.php'))) {
            $nameSpaceAdminProduct = 'App\GP247\Shop\Api\Admin';
        } else {
            $nameSpaceAdminProduct = 'GP247\Shop\Api\Admin';
        }
        Route::group([
            'prefix' => 'product',
        ], function () use($nameSpaceAdminProduct) {
            Route::get('list', $nameSpaceAdminProduct.'\AdminProductController@getProductList');
            Route::get('detail/{id}', $nameSpaceAdminProduct.'\AdminProductController@getProductDetail');
        });

    // Brand
        if (file_exists(app_path('GP247/Shop/Api/Admin/AdminBrandController.php'))) {
            $nameSpaceAdminBrand = 'App\GP247\Shop\Api\Admin';
        } else {
            $nameSpaceAdminBrand = 'GP247\Shop\Api\Admin';
        }
        Route::group([
            'prefix' => 'brand',
        ], function () use($nameSpaceAdminBrand) {
            Route::get('list', $nameSpaceAdminBrand.'\AdminBrandController@getBrandList');
            Route::get('detail/{id}', $nameSpaceAdminBrand.'\AdminBrandController@getBrandDetail');
        });

    // Supplier
        if (file_exists(app_path('GP247/Shop/Api/Admin/AdminSupplierController.php'))) {
            $nameSpaceAdminSupplier = 'App\GP247\Shop\Api\Admin';
        } else {
            $nameSpaceAdminSupplier = 'GP247\Shop\Api\Admin';
        }
        Route::group([
            'prefix' => 'supplier',
        ], function () use($nameSpaceAdminSupplier) {
            Route::get('list', $nameSpaceAdminSupplier.'\AdminSupplierController@getSupplierList');
            Route::get('detail/{id}', $nameSpaceAdminSupplier.'\AdminSupplierController@getSupplierDetail');
        });

});
