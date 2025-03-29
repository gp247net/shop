<?php
use Illuminate\Support\Facades\Route;
if (file_exists(app_path('GP247/Shop/Admin/Controllers/AdminReportController.php'))) {
    $nameSpaceAdminReport = 'App\GP247\Shop\Admin\Controllers';
} else {
    $nameSpaceAdminReport = 'GP247\Shop\Admin\Controllers';
}
Route::group(['prefix' => 'report'], function () use ($nameSpaceAdminReport) {
    Route::get('/product', $nameSpaceAdminReport.'\AdminReportController@product')->name('admin_report.product');
});