<?php
use GP247\Shop\Admin\Livewire\AttributeGroupManager;
use Illuminate\Support\Facades\Route;

// AttributeGroup — cutover (PA-1): legacy URLs render the modern Livewire
// two-panel manager in-place. POST create/edit/delete removed; RBAC unchanged.
Route::group(['prefix' => 'attribute_group'], function () {
    Route::get('/', AttributeGroupManager::class)->name('admin_attribute_group.index');
    Route::get('/edit/{id}', AttributeGroupManager::class)->name('admin_attribute_group.edit');
});
