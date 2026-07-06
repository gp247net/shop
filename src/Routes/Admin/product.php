<?php
use GP247\Shop\Admin\Livewire\ProductManager;
use Illuminate\Support\Facades\Route;

// Product — cutover (PA-1): legacy URLs render the modern Livewire manager
// in-place. The Livewire component handles list, create, and edit (all types)
// on one page; separate create/build/group GET routes still exist for deep
// links. POST create/edit/delete/clone removed; RBAC slug unchanged.
Route::group(['prefix' => 'product'], function () {
    Route::get('/', ProductManager::class)->name('admin_product.index');
    Route::get('create', ProductManager::class)->name('admin_product.create');
    Route::get('build_create', ProductManager::class)->name('admin_product.build_create');
    Route::get('group_create', ProductManager::class)->name('admin_product.group_create');
    Route::get('/edit/{id}', ProductManager::class)->name('admin_product.edit');
});
