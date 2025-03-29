<?php
use Illuminate\Support\Facades\Route;
if (file_exists(app_path('GP247/Shop/Admin/Controllers/AdminTaxController.php'))) {
    $nameSpaceAdminTax = 'App\GP247\Shop\Admin\Controllers';
} else {
    $nameSpaceAdminTax = 'GP247\Shop\Admin\Controllers';
}
Route::group(['prefix' => 'tax'], function () use ($nameSpaceAdminTax) {
    Route::get('/', $nameSpaceAdminTax.'\AdminTaxController@index')->name('admin_tax.index');
    Route::get('create', function () {
        return redirect()->route('admin_tax.index');
    });
    Route::post('/create', $nameSpaceAdminTax.'\AdminTaxController@postCreate')->name('admin_tax.post_create');
    Route::get('/edit/{id}', $nameSpaceAdminTax.'\AdminTaxController@edit')->name('admin_tax.edit');
    Route::post('/edit/{id}', $nameSpaceAdminTax.'\AdminTaxController@postEdit')->name('admin_tax.post_edit');
    Route::post('/delete', $nameSpaceAdminTax.'\AdminTaxController@deleteList')->name('admin_tax.delete');
});