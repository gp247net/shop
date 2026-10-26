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
