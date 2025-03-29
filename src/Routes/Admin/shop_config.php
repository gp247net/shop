<?php
use Illuminate\Support\Facades\Route;
if (file_exists(app_path('GP247/Shop/Admin/Controllers/AdminShopConfigController.php'))) {
    $nameSpaceAdminShopConfig = 'App\GP247\Shop\Admin\Controllers';
} else {
    $nameSpaceAdminShopConfig = 'GP247\Shop\Admin\Controllers';
}
Route::group(['prefix' => 'shop_config'], function () use ($nameSpaceAdminShopConfig) {
    Route::get('/', $nameSpaceAdminShopConfig.'\AdminShopConfigController@index')->name('admin_shop_config.index');
});