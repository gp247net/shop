<?php
use GP247\Shop\Admin\Livewire\CurrencyManager;
use Illuminate\Support\Facades\Route;

// Currency — cutover (PA-1): legacy URLs render the modern Livewire two-panel
// manager in-place. POST create/edit/delete removed; RBAC slug unchanged.
Route::group(['prefix' => 'currency'], function () {
    Route::get('/', CurrencyManager::class)->name('admin_currency.index');
    Route::get('/edit/{id}', CurrencyManager::class)->name('admin_currency.edit');
});
