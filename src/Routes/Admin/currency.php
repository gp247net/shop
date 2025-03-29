<?php
use Illuminate\Support\Facades\Route;
if (file_exists(app_path('GP247/Shop/Admin/Controllers/AdminCurrencyController.php'))) {
    $nameSpaceAdminCurrency = 'App\GP247\Shop\Admin\Controllers';
} else {
    $nameSpaceAdminCurrency = 'GP247\Shop\Admin\Controllers';
}
Route::group(['prefix' => 'currency'], function () use ($nameSpaceAdminCurrency) {
    Route::get('/', $nameSpaceAdminCurrency.'\AdminCurrencyController@index')->name('admin_currency.index');
    Route::get('/create', $nameSpaceAdminCurrency.'\AdminCurrencyController@create')->name('admin_currency.create');
    Route::post('/create', $nameSpaceAdminCurrency.'\AdminCurrencyController@postCreate')->name('admin_currency.post_create');
    Route::get('/edit/{id}', $nameSpaceAdminCurrency.'\AdminCurrencyController@edit')->name('admin_currency.edit');
    Route::post('/edit/{id}', $nameSpaceAdminCurrency.'\AdminCurrencyController@postEdit')->name('admin_currency.post_edit');
    Route::post('/delete', $nameSpaceAdminCurrency.'\AdminCurrencyController@deleteList')->name('admin_currency.delete');
});