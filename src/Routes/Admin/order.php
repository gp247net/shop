<?php

use GP247\Shop\Admin\Livewire\OrderManager;
use GP247\Shop\Admin\Controllers\AdminOrderController;
use Illuminate\Support\Facades\Route;

// Order — cutover (PA-1): list/detail/create routes render the modern Livewire
// OrderManager in-place. The legacy x-editable mutation endpoints (add_item,
// edit_item, delete_item, update) were removed with their controller methods —
// header/totals/line-item editing is Livewire actions on OrderManager
// (US-SADM-order-info-edit, D3). The create screen keeps its controller
// endpoints (post_create, product_search, user_info) and the invoice utility
// stays. The product_info endpoint (getInfoProduct) was removed with the
// legacy Bootstrap attribute picker — the create + edit screens now select
// attributes via the product_search JSON / Livewire state
// (US-SADM-order-item-attribute-select, D5). The bulk-delete endpoint
// (admin_order.delete → deleteList) was removed with the dead v1 index() —
// it reported success even when the model guard refused, and orders are
// deleted one at a time via OrderManager::delete() by decision
// (US-SADM-order-delete-money-guard AC7, mod 20260830T091705, QĐ-1=A).
// RBAC slug unchanged.
Route::group(['prefix' => 'order'], function () {
    Route::get('/', OrderManager::class)->name('admin_order.index');
    Route::get('/detail/{id}', OrderManager::class)->name('admin_order.detail');
    Route::get('create', AdminOrderController::class . '@create')->name('admin_order.create');

    Route::post('/create', AdminOrderController::class . '@postCreate')->name('admin_order.post_create');
    Route::get('/product_search', AdminOrderController::class . '@getSearchProduct')->name('admin_order.product_search');
    Route::get('/user_info', AdminOrderController::class . '@getInfoUser')->name('admin_order.user_info');
    Route::get('/invoice', AdminOrderController::class . '@invoice')->name('admin_order.invoice');
});
