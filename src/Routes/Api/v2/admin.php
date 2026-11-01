<?php

use GP247\Core\Api\Support\ApiAbilities;
use GP247\Shop\Api\V2\AdminController;
use Illuminate\Support\Facades\Route;

// REST API v2 — admin data of shop; each endpoint declares its admin screen
// (api.admin:<admin route name>, same mapping as v1 — US-RBAC-api-route-permission).
Route::group([
    'prefix'     => GP247_API_V2_CORE_PREFIX,
    'middleware' => ['auth:admin-api', ApiAbilities::admin()],
], function () {
    $admin = gp247_namespace(AdminController::class);
    $map = [
        'customer' => ['admin_customer.index', 'admin_customer.index'],
        'order'    => ['admin_order.index', 'admin_order.detail'],
        'product'  => ['admin_product.index', 'admin_product.index'],
        'category' => ['admin_category.index', 'admin_category.index'],
        'brand'    => ['admin_brand.index', 'admin_brand.index'],
        'supplier' => ['admin_supplier.index', 'admin_supplier.index'],
    ];
    foreach ($map as $resource => [$listScreen, $detailScreen]) {
        Route::get($resource.'/list', $admin.'@'.$resource.'List')->middleware('api.admin:'.$listScreen);
        Route::get($resource.'/detail/{id}', $admin.'@'.$resource.'Detail')->middleware('api.admin:'.$detailScreen);
    }
});

// Admin API writes on orders (US-api-admin-write-order-status / -shipping / -note): permission =
// a write on the Orders screen, checked like the screen's own actions; per-account write limit;
// an Idempotency-Key is accepted (a retry answers the first result).
Route::group([
    'prefix'     => GP247_API_V2_CORE_PREFIX,
    'middleware' => ['auth:admin-api', ApiAbilities::admin(), 'api.admin:admin_order.index,write', 'throttle:gp247-api-write', 'api.idempotent:optional'],
], function () {
    $orders = gp247_namespace(\GP247\Shop\Api\V2\OrderWriteController::class);
    Route::post('order/{id}/status', $orders.'@status');
    Route::post('order/{id}/shipping', $orders.'@shipping');
    Route::post('order/{id}/notes', $orders.'@notes');
});

// Admin API writes on products and stock (US-api-admin-write-product-fields / -stock-*): a write
// on the Products screen. Stock commands require an Idempotency-Key (a retried receipt must not
// move the stock twice).
Route::group([
    'prefix'     => GP247_API_V2_CORE_PREFIX,
    'middleware' => ['auth:admin-api', ApiAbilities::admin(), 'api.admin:admin_product.index,write', 'throttle:gp247-api-write'],
], function () {
    $products = gp247_namespace(\GP247\Shop\Api\V2\ProductWriteController::class);
    $stock = gp247_namespace(\GP247\Shop\Api\V2\StockController::class);
    Route::patch('product/{id}', $products.'@update')->middleware('api.idempotent:optional');
    Route::post('product/{id}/stock', $stock.'@single')->middleware('api.idempotent');
    Route::post('stock/batch', $stock.'@batch')->middleware('api.idempotent');
});

// Admin API lock / unlock of a customer (US-api-admin-write-customer-status): a write on the
// Customers screen; an Idempotency-Key is accepted.
Route::group([
    'prefix'     => GP247_API_V2_CORE_PREFIX,
    'middleware' => ['auth:admin-api', ApiAbilities::admin(), 'api.admin:admin_customer.index,write', 'throttle:gp247-api-write', 'api.idempotent:optional'],
], function () {
    Route::patch('customer/{id}', gp247_namespace(\GP247\Shop\Api\V2\CustomerWriteController::class).'@update');
});
