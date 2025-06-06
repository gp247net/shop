<?php
use Illuminate\Support\Facades\Route;
if (file_exists(app_path('GP247/Shop/Admin/Controllers/AdminOrderController.php'))) {
    $nameSpaceAdminOrder = 'App\GP247\Shop\Admin\Controllers';
} else {
    $nameSpaceAdminOrder = 'GP247\Shop\Admin\Controllers';
}
Route::group(['prefix' => 'order'], function () use ($nameSpaceAdminOrder) {
    Route::get('/', $nameSpaceAdminOrder.'\AdminOrderController@index')->name('admin_order.index');
    Route::get('/detail/{id}', $nameSpaceAdminOrder.'\AdminOrderController@detail')->name('admin_order.detail');
    Route::get('create', $nameSpaceAdminOrder.'\AdminOrderController@create')->name('admin_order.create');
    Route::post('/create', $nameSpaceAdminOrder.'\AdminOrderController@postCreate')->name('admin_order.post_create');
    Route::post('/add_item', $nameSpaceAdminOrder.'\AdminOrderController@postAddItem')->name('admin_order.post_add_item');
    Route::post('/edit_item', $nameSpaceAdminOrder.'\AdminOrderController@postEditItem')->name('admin_order.post_edit_item');
    Route::post('/delete_item', $nameSpaceAdminOrder.'\AdminOrderController@postDeleteItem')->name('admin_order.post_delete_item');
    Route::post('/update', $nameSpaceAdminOrder.'\AdminOrderController@postOrderUpdate')->name('admin_order.post_update');
    Route::post('/delete', $nameSpaceAdminOrder.'\AdminOrderController@deleteList')->name('admin_order.delete');
    Route::get('/product_info', $nameSpaceAdminOrder.'\AdminOrderController@getInfoProduct')->name('admin_order.product_info');
    Route::get('/user_info', $nameSpaceAdminOrder.'\AdminOrderController@getInfoUser')->name('admin_order.user_info');
    Route::get('/invoice', $nameSpaceAdminOrder.'\AdminOrderController@invoice')->name('admin_order.invoice');
});