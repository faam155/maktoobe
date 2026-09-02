<?php

use App\Http\Controllers\Admin\BrandGuidelineController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PromptCategoryController;
use App\Http\Controllers\Admin\PromptController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\EventCalendarController;
use App\Http\Controllers\EventFileController;
use App\Http\Controllers\EventReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'active', 'verified', 'permission:access-admin'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/permissions', PermissionController::class)->name('permissions.index');
    Route::resource('users', UserController::class)->only(['index', 'create', 'show', 'edit']);
    Route::resource('roles', RoleController::class)->only(['index', 'create', 'show', 'edit']);
    Route::resource('prompt-categories', PromptCategoryController::class)->only(['index', 'create', 'edit']);
    Route::resource('prompts', PromptController::class)->only(['index', 'create', 'show', 'edit']);

    Route::middleware('recent-auth')->group(function () {
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/status', [UserController::class, 'status'])->name('users.status');
        Route::put('/users/{user}/roles', [UserController::class, 'roles'])->name('users.roles');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::post('/prompt-categories', [PromptCategoryController::class, 'store'])->name('prompt-categories.store');
        Route::put('/prompt-categories/{promptCategory}', [PromptCategoryController::class, 'update'])->name('prompt-categories.update');
        Route::patch('/prompt-categories/{promptCategory}/status', [PromptCategoryController::class, 'status'])->name('prompt-categories.status');
        Route::patch('/prompt-categories/{promptCategory}/move', [PromptCategoryController::class, 'move'])->name('prompt-categories.move');
        Route::delete('/prompt-categories/{promptCategory}', [PromptCategoryController::class, 'destroy'])->name('prompt-categories.destroy');
        Route::post('/prompts', [PromptController::class, 'store'])->name('prompts.store');
        Route::put('/prompts/{prompt}', [PromptController::class, 'update'])->name('prompts.update');
        Route::patch('/prompts/{prompt}/status', [PromptController::class, 'status'])->name('prompts.status');
        Route::post('/prompts/{prompt}/duplicate', [PromptController::class, 'duplicate'])->name('prompts.duplicate');
        Route::delete('/prompts/{prompt}', [PromptController::class, 'destroy'])->name('prompts.destroy');
    });
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'active', 'verified', 'permission:manage-events'])->group(function () {
    Route::get('/calendar', EventCalendarController::class)->name('calendar');
    Route::get('/events/{event}/reports', [EventReportController::class, 'index'])->name('events.reports.index');
    Route::get('/events/{event}/reports/{report}/versions/{version}/download', [EventReportController::class, 'download'])->name('events.reports.download');
    Route::post('/events/{event}/reports', [EventReportController::class, 'store'])->middleware(['recent-auth', 'throttle:20,1'])->name('events.reports.store');
    Route::delete('/events/{event}/reports/{report}', [EventReportController::class, 'destroy'])->middleware(['recent-auth', 'throttle:20,1'])->name('events.reports.destroy');
    Route::get('/events/{event}/files', [EventFileController::class, 'index'])->name('events.files.index');
    Route::get('/events/{event}/files/{file}/download', [EventFileController::class, 'download'])->name('events.files.download');
    Route::get('/events/{event}/files/{file}/preview', [EventFileController::class, 'preview'])->name('events.files.preview');
    Route::middleware(['recent-auth', 'throttle:20,1'])->group(function () {
        Route::post('/events/{event}/files', [EventFileController::class, 'store'])->name('events.files.store');
        Route::patch('/events/{event}/files/{file}', [EventFileController::class, 'update'])->name('events.files.update');
        Route::delete('/events/{event}/files/{file}', [EventFileController::class, 'destroy'])->name('events.files.destroy');
    });
    Route::resource('events', EventController::class)->only(['index', 'create', 'show', 'edit']);
    Route::middleware('recent-auth')->group(function () {
        Route::post('/events', [EventController::class, 'store'])->name('events.store');
        Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::patch('/events/{event}/status', [EventController::class, 'status'])->name('events.status');
        Route::post('/events/{event}/duplicate', [EventController::class, 'duplicate'])->name('events.duplicate');
        Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    });
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'active', 'verified', 'permission:manage-brand-guidelines'])->group(function () {
    Route::resource('brand-guidelines', BrandGuidelineController::class)->only(['index', 'create', 'show']);
    Route::get('/brand-guideline-versions/{version}/download', [BrandGuidelineController::class, 'download'])->name('brand-guidelines.download');
    Route::middleware('recent-auth')->group(function () {
        Route::post('/brand-guidelines', [BrandGuidelineController::class, 'store'])->name('brand-guidelines.store');
        Route::post('/brand-guidelines/{brandGuideline}/versions', [BrandGuidelineController::class, 'version'])->name('brand-guidelines.versions.store');
        Route::patch('/brand-guideline-versions/{version}/status', [BrandGuidelineController::class, 'status'])->name('brand-guidelines.status');
    });
});
