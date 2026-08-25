<?php
use GP247\Shop\Admin\Livewire\ProductTagManager;
use Illuminate\Support\Facades\Route;

// Product keyword-tag manager (US-SADM-product-tags): two-panel Livewire CRUD for the
// shop_product_tag taxonomy, gated by the `admin_product` permission (same as products).
Route::group(['prefix' => 'product_tag'], function () {
    Route::get('/', ProductTagManager::class)->name('admin_product_tag.index');
    Route::get('/edit/{id}', ProductTagManager::class)->name('admin_product_tag.edit');
});
