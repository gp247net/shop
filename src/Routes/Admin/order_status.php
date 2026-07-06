<?php
use GP247\Shop\Admin\Livewire\OrderStatusManager;
use Illuminate\Support\Facades\Route;

// OrderStatus — cutover (PA-1): legacy URLs render the modern Livewire
// status manager in-place. POST create/edit/delete removed; RBAC unchanged.
Route::group(['prefix' => 'order_status'], function () {
    Route::get('/', OrderStatusManager::class)->name('admin_order_status.index');
    Route::get('/edit/{id}', OrderStatusManager::class)->name('admin_order_status.edit');
});
