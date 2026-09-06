<?php
use Illuminate\Support\Facades\Route;

// ShopConfig is now a tab on the core Configuration hub (store_config) via the
// SettingsHubTabRegistry seam (US-SADM-shop-config-into-hub, mod 20260906T104800).
// The legacy URL + route name are kept for back-compat but redirect to the hub, so
// there is a single entry point. A role scoped only to shop_config still lands on
// the hub and sees just the shop tab (SettingsHub inclusive entry).
Route::group(['prefix' => 'shop_config'], function () {
    Route::get('/', function () {
        return redirect()->route('admin_config.index');
    })->name('admin_shop_config.index');
});
