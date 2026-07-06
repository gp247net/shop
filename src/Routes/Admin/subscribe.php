<?php
use GP247\Shop\Admin\Livewire\SubscribeManager;
use Illuminate\Support\Facades\Route;

// Subscribe — cutover (PA-1): legacy URLs render the modern Livewire two-panel
// manager in-place. POST create/edit/delete removed; RBAC slug unchanged.
Route::group(['prefix' => 'subscribe'], function () {
    Route::get('/', SubscribeManager::class)->name('admin_subscribe.index');
    Route::get('/edit/{id}', SubscribeManager::class)->name('admin_subscribe.edit');
});
