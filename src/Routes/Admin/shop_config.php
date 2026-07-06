<?php
use GP247\Shop\Admin\Livewire\ShopConfigForm;
use Illuminate\Support\Facades\Route;

// ShopConfig — cutover (PA-1): legacy URL renders the modern Livewire ShopConfigForm.
// RBAC slug unchanged.
Route::group(['prefix' => 'shop_config'], function () {
    Route::get('/', ShopConfigForm::class)->name('admin_shop_config.index');
});
