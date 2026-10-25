<?php

use GP247\Shop\Admin\Livewire\ReportManager;
use Illuminate\Support\Facades\Route;

// Report — cutover (PA-1): legacy URL renders the modern Livewire ReportManager.
// RBAC slug unchanged.
Route::group(['prefix' => 'report'], function () {
    Route::get('/product', gp247_namespace(ReportManager::class))->name('admin_report.product');
});
