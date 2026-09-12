<?php

use App\Http\Controllers\Social\DashboardController;
use App\Http\Controllers\Social\DataController;
use App\Http\Controllers\Social\PostController;
use App\Http\Controllers\Social\ReportController;
use App\Http\Controllers\Social\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('app/social-media/linkedin')->name('social.')->middleware(['auth', 'active', 'verified', 'throttle:social-read'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');
    Route::get('/accounts', [WorkspaceController::class, 'accounts'])->name('accounts');
    Route::post('/accounts', [WorkspaceController::class, 'accountSave'])->middleware('recent-auth')->name('accounts.save');
    Route::get('/{account}/dashboard', [DashboardController::class, 'show'])->name('dashboard');
    Route::get('/{account}/posts', [PostController::class, 'index'])->name('posts');
    Route::get('/{account}/posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::get('/{account}/posts/{post}', [PostController::class, 'show'])->name('posts.show');
    Route::get('/{account}/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::get('/{account}/catalog/{kind}', [WorkspaceController::class, 'index'])->name('catalog');
    Route::get('/{account}/settings', [WorkspaceController::class, 'settings'])->name('settings');
    Route::get('/{account}/metrics', [DataController::class, 'metrics'])->name('metrics');
    Route::get('/{account}/imports', [DataController::class, 'imports'])->name('imports');
    Route::get('/{account}/imports/template', [DataController::class, 'template'])->name('imports.template');
    Route::get('/{account}/sync', [DataController::class, 'logs'])->name('sync');
    Route::get('/{account}/reports', [ReportController::class, 'index'])->name('reports');
    Route::get('/{account}/export/{format}', [ReportController::class, 'export'])->whereIn('format', ['pdf', 'xlsx'])->middleware('throttle:social-export')->name('export');
    Route::middleware(['recent-auth', 'throttle:social-write'])->group(function () {
        Route::post('/{account}/targets/{target}/distribute', [WorkspaceController::class, 'distribute'])->name('targets.distribute');
        Route::post('/{account}/posts', [PostController::class, 'save'])->name('posts.store');
        Route::put('/{account}/posts/{post}', [PostController::class, 'save'])->name('posts.update');
        Route::delete('/{account}/posts/{post}', [PostController::class, 'delete'])->name('posts.delete');
        Route::post('/{account}/catalog/{kind}', [WorkspaceController::class, 'save'])->name('catalog.save');
        Route::delete('/{account}/catalog/{kind}/{id}', [WorkspaceController::class, 'delete'])->name('catalog.delete');
        Route::post('/{account}/settings', [WorkspaceController::class, 'settingsSave'])->name('settings.save');
        Route::post('/{account}/metrics', [DataController::class, 'metricSave'])->name('metrics.save');
        Route::post('/{account}/imports', [DataController::class, 'preview'])->name('imports.preview');
        Route::post('/{account}/imports/{import}/confirm', [DataController::class, 'confirm'])->name('imports.confirm');
        Route::post('/{account}/sync', [DataController::class, 'sync'])->name('sync.start');
        Route::post('/{account}/reports', [ReportController::class, 'save'])->name('reports.save');
        Route::post('/{account}/insights', [ReportController::class, 'generate'])->middleware('throttle:social-ai')->name('insights.generate');
    });
});
