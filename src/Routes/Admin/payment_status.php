<?php
use GP247\Shop\Admin\Livewire\PaymentStatusManager;
use Illuminate\Support\Facades\Route;

// PaymentStatus — cutover (PA-1): legacy URLs render the modern Livewire
// status manager in-place. POST create/edit/delete removed; RBAC unchanged.
Route::group(['prefix' => 'payment_status'], function () {
    Route::get('/', gp247_namespace(PaymentStatusManager::class))->name('admin_payment_status.index');
    Route::get('/edit/{id}', gp247_namespace(PaymentStatusManager::class))->name('admin_payment_status.edit');
});
