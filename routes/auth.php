<?php

use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LocaleController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\MyPromptController;
use App\Http\Controllers\Portal\PromptFavoriteController;
use App\Http\Controllers\Portal\PromptLibraryController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;

Route::post('/locale', LocaleController::class)->middleware('throttle:30,1')->name('locale.update');
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:registration')->name('register.store');
    Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
    Route::post('/forgot-password', [PasswordRecoveryController::class, 'store'])->middleware('throttle:recovery')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:recovery')->name('password.update');
    Route::view('/otp', 'auth.otp-request')->name('otp.request');
    Route::post('/otp', [OtpController::class, 'request'])->middleware('throttle:otp-request')->name('otp.send');
    Route::get('/otp/verify', [OtpController::class, 'show'])->name('otp.verify');
    Route::post('/otp/verify', [OtpController::class, 'verify'])->middleware('throttle:otp-verify')->name('otp.check');
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->middleware('throttle:recovery')->name('google.redirect');
});
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->middleware('throttle:recovery')->name('google.callback');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/email/verify', [VerificationController::class, 'show'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [VerificationController::class, 'resend'])->middleware('throttle:1,1')->name('verification.send');
    Route::view('/account/pending', 'auth.pending')->name('account.pending');
    Route::middleware(['active', 'verified'])->group(function () {
        Route::get('/app', DashboardController::class)->name('account.home');
        Route::get('/app/prompts', [PromptLibraryController::class, 'index'])->name('prompts.index');
        Route::get('/app/prompts/{prompt}', [PromptLibraryController::class, 'show'])->name('prompts.show');
        Route::post('/app/prompts/{prompt}/copy', [PromptLibraryController::class, 'copy'])->middleware('throttle:60,1')->name('prompts.copy');
        Route::post('/app/prompts/{prompt}/favorite', [PromptFavoriteController::class, 'store'])->middleware('throttle:60,1')->name('prompts.favorite');
        Route::delete('/app/prompts/{prompt}/favorite', [PromptFavoriteController::class, 'destroy'])->middleware('throttle:60,1')->name('prompts.unfavorite');
        Route::get('/app/my-prompts', [MyPromptController::class, 'index'])->name('my-prompts.index');
        Route::get('/app/my-prompts/create', [MyPromptController::class, 'create'])->name('my-prompts.create');
        Route::post('/app/my-prompts', [MyPromptController::class, 'store'])->name('my-prompts.store');
        Route::get('/app/my-prompts/{prompt}', [MyPromptController::class, 'show'])->name('my-prompts.show');
        Route::get('/app/my-prompts/{prompt}/edit', [MyPromptController::class, 'edit'])->name('my-prompts.edit');
        Route::put('/app/my-prompts/{prompt}', [MyPromptController::class, 'update'])->name('my-prompts.update');
        Route::delete('/app/my-prompts/{prompt}', [MyPromptController::class, 'destroy'])->name('my-prompts.destroy');
        Route::post('/app/my-prompts/{prompt}/duplicate', [MyPromptController::class, 'duplicate'])->name('my-prompts.duplicate');
        Route::post('/app/my-prompts/{prompt}/copy', [MyPromptController::class, 'copy'])->middleware('throttle:60,1')->name('my-prompts.copy');
        Route::get('/account/security', [AccountController::class, 'security'])->name('account.security');
        Route::view('/confirm-password', 'auth.confirm-password')->name('password.confirm');
        Route::post('/confirm-password', [AccountController::class, 'confirm'])->middleware('throttle:login')->name('password.confirm.store');
        Route::middleware('recent-auth')->group(function () {
            Route::post('/account/sessions/revoke', [AccountController::class, 'revokeOthers'])->name('sessions.revoke');
            Route::post('/account/google', [GoogleController::class, 'link'])->middleware('throttle:recovery')->name('google.link');
            Route::post('/account/phone', [OtpController::class, 'enroll'])->middleware('throttle:otp-request')->name('phone.send');
            Route::get('/account/phone/verify', [OtpController::class, 'showEnrollment'])->name('phone.verify');
            Route::post('/account/phone/verify', [OtpController::class, 'verifyEnrollment'])->middleware('throttle:otp-verify')->name('phone.check');
        });
    });
});
