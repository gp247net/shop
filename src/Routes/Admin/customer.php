<?php
use GP247\Shop\Admin\Livewire\CustomerManager;
use Illuminate\Support\Facades\Route;

// Customer — cutover (PA-1): legacy URLs render the modern Livewire two-panel
// manager in-place. Address management is now handled inline via Livewire
// actions; the separate address GET/POST routes are removed. RBAC unchanged.
Route::group(['prefix' => 'customer'], function () {
    Route::get('/', CustomerManager::class)->name('admin_customer.index');
    Route::get('/edit/{id}', CustomerManager::class)->name('admin_customer.edit');
});
