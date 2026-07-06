<?php
use GP247\Shop\Admin\Livewire\TaxManager;
use Illuminate\Support\Facades\Route;

// Tax — cutover (PA-1): the legacy URLs render the modern Livewire two-panel
// manager in-place (keep route name + path + http_uri). The manager handles
// list + inline form on one page, so create is inline and CRUD mutations flow
// through livewire/update; the POST create/edit/delete routes are removed. RBAC
// slug derives from the component (admin_tax), so authz is unchanged.
Route::group(['prefix' => 'tax'], function () {
    Route::get('/', TaxManager::class)->name('admin_tax.index');
    Route::get('/edit/{id}', TaxManager::class)->name('admin_tax.edit');
});
