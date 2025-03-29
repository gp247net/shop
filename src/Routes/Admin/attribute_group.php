<?php
use Illuminate\Support\Facades\Route;
if (file_exists(app_path('GP247/Shop/Admin/Controllers/AdminAttributeGroupController.php'))) {
    $nameSpaceAdminAttribute = 'App\GP247\Shop\Admin\Controllers';
} else {
    $nameSpaceAdminAttribute = 'GP247\Shop\Admin\Controllers';
}
Route::group(['prefix' => 'attribute_group'], function () use ($nameSpaceAdminAttribute) {
    Route::get('/', $nameSpaceAdminAttribute.'\AdminAttributeGroupController@index')->name('admin_attribute_group.index');
    Route::get('create', function () {
        return redirect()->route('admin_attribute_group.index');
    });
    Route::post('/create', $nameSpaceAdminAttribute.'\AdminAttributeGroupController@postCreate')->name('admin_attribute_group.post_create');
    Route::get('/edit/{id}', $nameSpaceAdminAttribute.'\AdminAttributeGroupController@edit')->name('admin_attribute_group.edit');
    Route::post('/edit/{id}', $nameSpaceAdminAttribute.'\AdminAttributeGroupController@postEdit')->name('admin_attribute_group.post_edit');
    Route::post('/delete', $nameSpaceAdminAttribute.'\AdminAttributeGroupController@deleteList')->name('admin_attribute_group.delete');
});