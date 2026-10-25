<?php
use GP247\Shop\Admin\Livewire\ShippingStatusManager;
use Illuminate\Support\Facades\Route;

// ShippingStatus — cutover (PA-1): legacy URLs render the modern Livewire
// status manager in-place. POST create/edit/delete removed; RBAC unchanged.
Route::group(['prefix' => 'shipping_status'], function () {
    Route::get('/', gp247_namespace(ShippingStatusManager::class))->name('admin_shipping_status.index');
    Route::get('/edit/{id}', gp247_namespace(ShippingStatusManager::class))->name('admin_shipping_status.edit');
});
