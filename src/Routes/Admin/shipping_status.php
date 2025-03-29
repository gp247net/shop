<?php
use Illuminate\Support\Facades\Route;
if (file_exists(app_path('GP247/Shop/Admin/Controllers/AdminShipingStatusController.php'))) {
    $nameSpaceAdminShippingStatus = 'App\GP247\Shop\Admin\Controllers';
} else {
    $nameSpaceAdminShippingStatus = 'GP247\Shop\Admin\Controllers';
}
Route::group(['prefix' => 'shipping_status'], function () use ($nameSpaceAdminShippingStatus) {
    Route::get('/', $nameSpaceAdminShippingStatus.'\AdminShipingStatusController@index')
        ->name('admin_shipping_status.index');
    Route::get('create', function () {
        return redirect()->route('admin_shipping_status.index');
    });
    Route::post('/create', $nameSpaceAdminShippingStatus.'\AdminShipingStatusController@postCreate')
        ->name('admin_shipping_status.post_create');
    Route::get('/edit/{id}', $nameSpaceAdminShippingStatus.'\AdminShipingStatusController@edit')
        ->name('admin_shipping_status.edit');
    Route::post('/edit/{id}', $nameSpaceAdminShippingStatus.'\AdminShipingStatusController@postEdit')
        ->name('admin_shipping_status.post_edit');
    Route::post('/delete', $nameSpaceAdminShippingStatus.'\AdminShipingStatusController@deleteList')
        ->name('admin_shipping_status.delete');
});