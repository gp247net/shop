<?php

use GP247\Shop\Admin\Livewire\PaymentRequestManager;

// Payment requests (mod 20260929T060454, slice S1): two-panel manager; the record
// being edited is carried in ?edit=<id> (keepStateOnSave) or the edit/{id} segment.
Route::group(['prefix' => 'payment_request'], function () {
    Route::get('/', gp247_namespace(PaymentRequestManager::class))->name('admin.payment_request.index');
    Route::get('edit/{id}', gp247_namespace(PaymentRequestManager::class))->name('admin.payment_request.edit');
});
