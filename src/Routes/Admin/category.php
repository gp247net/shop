<?php
use GP247\Shop\Admin\Livewire\CategoryManager;
use Illuminate\Support\Facades\Route;

// Category — cutover (PA-1): legacy URLs render the modern Livewire two-panel
// manager in-place. POST create/edit/delete removed; RBAC slug unchanged.
Route::group(['prefix' => 'category'], function () {
    Route::get('/', CategoryManager::class)->name('admin_category.index');
    Route::get('/edit/{id}', CategoryManager::class)->name('admin_category.edit');
});
