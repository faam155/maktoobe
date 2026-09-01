<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PromptCategoryController;
use App\Http\Controllers\Admin\PromptController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
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
