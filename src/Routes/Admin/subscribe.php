<?php
use Illuminate\Support\Facades\Route;
if (file_exists(app_path('GP247/Shop/Admin/Controllers/AdminSubscribeController.php'))) {
    $nameSpaceAdminSubscribe = 'App\GP247\Shop\Admin\Controllers';
} else {
    $nameSpaceAdminSubscribe = 'GP247\Shop\Admin\Controllers';
}
Route::group(['prefix' => 'subscribe'], function () use ($nameSpaceAdminSubscribe) {
    Route::get('/', $nameSpaceAdminSubscribe.'\AdminSubscribeController@index')->name('admin_subscribe.index');
    Route::get('create', $nameSpaceAdminSubscribe.'\AdminSubscribeController@create')->name('admin_subscribe.create');
    Route::post('/create', $nameSpaceAdminSubscribe.'\AdminSubscribeController@postCreate')->name('admin_subscribe.post_create');
    Route::get('/edit/{id}', $nameSpaceAdminSubscribe.'\AdminSubscribeController@edit')->name('admin_subscribe.edit');
    Route::post('/edit/{id}', $nameSpaceAdminSubscribe.'\AdminSubscribeController@postEdit')->name('admin_subscribe.post_edit');
    Route::post('/delete', $nameSpaceAdminSubscribe.'\AdminSubscribeController@deleteList')->name('admin_subscribe.delete');
});