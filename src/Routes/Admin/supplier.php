<?php
use Illuminate\Support\Facades\Route;
if (file_exists(app_path('GP247/Shop/Admin/Controllers/AdminSupplierController.php'))) {
    $nameSpaceAdminSupplier = 'App\GP247\Shop\Admin\Controllers';
} else {
    $nameSpaceAdminSupplier = 'GP247\Shop\Admin\Controllers';
}
Route::group(['prefix' => 'supplier'], function () use ($nameSpaceAdminSupplier) {
    Route::get('/', $nameSpaceAdminSupplier.'\AdminSupplierController@index')->name('admin_supplier.index');
    Route::get('create', function () {
        return redirect()->route('admin_supplier.index');
    });
    Route::post('/create', $nameSpaceAdminSupplier.'\AdminSupplierController@postCreate')->name('admin_supplier.post_create');
    Route::get('/edit/{id}', $nameSpaceAdminSupplier.'\AdminSupplierController@edit')->name('admin_supplier.edit');
    Route::post('/edit/{id}', $nameSpaceAdminSupplier.'\AdminSupplierController@postEdit')->name('admin_supplier.post_edit');
    Route::post('/delete', $nameSpaceAdminSupplier.'\AdminSupplierController@deleteList')->name('admin_supplier.delete');
});