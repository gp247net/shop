<?php
use Illuminate\Support\Facades\Route;
if (file_exists(app_path('GP247/Shop/Admin/Controllers/AdminBrandController.php'))) {
    $nameSpaceAdminBrand = 'App\GP247\Shop\Admin\Controllers';
} else {
    $nameSpaceAdminBrand = 'GP247\Shop\Admin\Controllers';
}
Route::group(['prefix' => 'brand'], function () use ($nameSpaceAdminBrand) {
    Route::get('/', $nameSpaceAdminBrand.'\AdminBrandController@index')->name('admin_brand.index');
    Route::get('create', function () {
        return redirect()->route('admin_brand.index');
    });
    Route::post('/create', $nameSpaceAdminBrand.'\AdminBrandController@postCreate')->name('admin_brand.post_create');
    Route::get('/edit/{id}', $nameSpaceAdminBrand.'\AdminBrandController@edit')->name('admin_brand.edit');
    Route::post('/edit/{id}', $nameSpaceAdminBrand.'\AdminBrandController@postEdit')->name('admin_brand.post_edit');
    Route::post('/delete', $nameSpaceAdminBrand.'\AdminBrandController@deleteList')->name('admin_brand.delete');
});