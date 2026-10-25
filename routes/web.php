<?php

use App\Http\Controllers\Admin\CustomizedPreviewController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\WordPressExportController;
use App\Http\Controllers\Api\PreviewApiController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\EditorController;
use App\Http\Controllers\SharedPreviewController;
use App\Http\Controllers\TemplateRenderController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index'])->name('home');

Route::get('editor/{template:slug}', [EditorController::class, 'show'])->name('editor.show');
Route::get('p/{uniqueCode}', [SharedPreviewController::class, 'show'])
    ->where('uniqueCode', '[A-Za-z0-9]{10}')
    ->name('previews.show');

Route::prefix('api/previews')->name('api.previews.')->middleware('throttle:60,1')->group(function () {
    Route::post('upload-image', [PreviewApiController::class, 'uploadImage'])->name('upload-image');
    Route::post('/', [PreviewApiController::class, 'store'])->name('store');
    Route::put('{uniqueCode}', [PreviewApiController::class, 'update'])->name('update');
    Route::post('{uniqueCode}/order-click', [PreviewApiController::class, 'orderClick'])->name('order-click');
});

Route::get('render-template/{template:slug}/{pageFile?}', [TemplateRenderController::class, 'show'])
    ->where('pageFile', '[A-Za-z0-9._-]+')
    ->name('templates.render');

Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::name('admin.')->group(function () {
        Route::post('templates/samples', [TemplateController::class, 'generateSamples'])->name('templates.samples');
        Route::post('templates/{template}/thumbnail', [TemplateController::class, 'updateThumbnail'])->name('templates.thumbnail');
        Route::resource('templates', TemplateController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('previews', [CustomizedPreviewController::class, 'index'])->name('previews.index');
        Route::patch('previews/{preview}/lock', [CustomizedPreviewController::class, 'toggleLock'])->name('previews.lock');

        Route::get('wordpress/export', [WordPressExportController::class, 'quick'])->name('wordpress.quick');
        Route::get('wordpress/previews/{preview:unique_code}', [WordPressExportController::class, 'preview'])->name('wordpress.preview');
        Route::get('wordpress/templates/{template}', [WordPressExportController::class, 'template'])->name('wordpress.template');

        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });
});

require __DIR__.'/settings.php';
