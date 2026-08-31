<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'active', 'verified', 'permission:access-admin'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/permissions', PermissionController::class)->name('permissions.index');
    Route::resource('users', UserController::class)->only(['index', 'create', 'show', 'edit']);
    Route::resource('roles', RoleController::class)->only(['index', 'create', 'show', 'edit']);

    Route::middleware('recent-auth')->group(function () {
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/status', [UserController::class, 'status'])->name('users.status');
        Route::put('/users/{user}/roles', [UserController::class, 'roles'])->name('users.roles');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });
});
