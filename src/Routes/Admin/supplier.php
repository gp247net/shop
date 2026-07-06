<?php
use GP247\Shop\Admin\Livewire\SupplierManager;
use Illuminate\Support\Facades\Route;

// Supplier — cutover (PA-1): legacy URLs render the modern Livewire two-panel
// manager in-place. POST create/edit/delete removed; RBAC slug unchanged.
Route::group(['prefix' => 'supplier'], function () {
    Route::get('/', SupplierManager::class)->name('admin_supplier.index');
    Route::get('/edit/{id}', SupplierManager::class)->name('admin_supplier.edit');
});
