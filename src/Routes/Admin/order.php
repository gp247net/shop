<?php

use GP247\Shop\Admin\Livewire\OrderManager;
use GP247\Shop\Admin\Controllers\AdminOrderController;
use Illuminate\Support\Facades\Route;

// Order — cutover (PA-1): list/detail/create routes render the modern Livewire
// OrderManager in-place. Non-GET mutation endpoints (add_item, edit_item,
// delete_item, update, delete) and utility GETs (product_info, user_info,
// invoice) are kept as legacy controller actions — OrderManager calls them
// via fetch/redirect internally. RBAC slug unchanged.
Route::group(['prefix' => 'order'], function () {
    Route::get('/', OrderManager::class)->name('admin_order.index');
    Route::get('/detail/{id}', OrderManager::class)->name('admin_order.detail');
    Route::get('create', AdminOrderController::class . '@create')->name('admin_order.create');

    // Legacy utility endpoints retained for OrderManager internal use.
    Route::post('/add_item', AdminOrderController::class . '@postAddItem')->name('admin_order.post_add_item');
    Route::post('/edit_item', AdminOrderController::class . '@postEditItem')->name('admin_order.post_edit_item');
    Route::post('/delete_item', AdminOrderController::class . '@postDeleteItem')->name('admin_order.post_delete_item');
    Route::post('/update', AdminOrderController::class . '@postOrderUpdate')->name('admin_order.post_update');
    Route::post('/delete', AdminOrderController::class . '@deleteList')->name('admin_order.delete');
    Route::post('/create', AdminOrderController::class . '@postCreate')->name('admin_order.post_create');
    Route::get('/product_info', AdminOrderController::class . '@getInfoProduct')->name('admin_order.product_info');
    Route::get('/user_info', AdminOrderController::class . '@getInfoUser')->name('admin_order.user_info');
    Route::get('/invoice', AdminOrderController::class . '@invoice')->name('admin_order.invoice');
});
