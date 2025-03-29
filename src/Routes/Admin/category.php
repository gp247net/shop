<?php
use Illuminate\Support\Facades\Route;
if (file_exists(app_path('GP247/Shop/Admin/Controllers/AdminCategoryController.php'))) {
    $nameSpaceAdminCategory = 'App\GP247\Shop\Admin\Controllers';
} else {
    $nameSpaceAdminCategory = 'GP247\Shop\Admin\Controllers';
}
Route::group(['prefix' => 'category'], function () use ($nameSpaceAdminCategory) {
    Route::get('/', $nameSpaceAdminCategory.'\AdminCategoryController@index')->name('admin_category.index');
    Route::get('create', $nameSpaceAdminCategory.'\AdminCategoryController@create')->name('admin_category.create');
    Route::post('/create', $nameSpaceAdminCategory.'\AdminCategoryController@postCreate')->name('admin_category.post_create');
    Route::get('/edit/{id}', $nameSpaceAdminCategory.'\AdminCategoryController@edit')->name('admin_category.edit');
    Route::post('/edit/{id}', $nameSpaceAdminCategory.'\AdminCategoryController@postEdit')->name('admin_category.post_edit');
    Route::post('/delete', $nameSpaceAdminCategory.'\AdminCategoryController@deleteList')->name('admin_category.delete');
});