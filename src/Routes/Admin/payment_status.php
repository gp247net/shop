<?php
use Illuminate\Support\Facades\Route;
if (file_exists(app_path('GP247/Shop/Admin/Controllers/AdminPaymentStatusController.php'))) {
    $nameSpaceAdminPaymentStatus = 'App\GP247\Shop\Admin\Controllers';
} else {
    $nameSpaceAdminPaymentStatus = 'GP247\Shop\Admin\Controllers';
}
Route::group(['prefix' => 'payment_status'], function () use ($nameSpaceAdminPaymentStatus) {
    Route::get('/', $nameSpaceAdminPaymentStatus.'\AdminPaymentStatusController@index')->name('admin_payment_status.index');
    Route::get('create', function () {
        return redirect()->route('admin_payment_status.index');
    });
    Route::post('/create', $nameSpaceAdminPaymentStatus.'\AdminPaymentStatusController@postCreate')->name('admin_payment_status.post_create');
    Route::get('/edit/{id}', $nameSpaceAdminPaymentStatus.'\AdminPaymentStatusController@edit')->name('admin_payment_status.edit');
    Route::post('/edit/{id}', $nameSpaceAdminPaymentStatus.'\AdminPaymentStatusController@postEdit')->name('admin_payment_status.post_edit');
    Route::post('/delete', $nameSpaceAdminPaymentStatus.'\AdminPaymentStatusController@deleteList')->name('admin_payment_status.delete');
});