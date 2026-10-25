<?php
use GP247\Shop\Admin\Livewire\BrandManager;
use Illuminate\Support\Facades\Route;

// Brand — cutover (PA-1): legacy URLs render the modern Livewire two-panel
// manager in-place. POST create/edit/delete removed; RBAC slug unchanged.
Route::group(['prefix' => 'brand'], function () {
    Route::get('/', gp247_namespace(BrandManager::class))->name('admin_brand.index');
    Route::get('/edit/{id}', gp247_namespace(BrandManager::class))->name('admin_brand.edit');
});
