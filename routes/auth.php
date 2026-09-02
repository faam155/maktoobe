<?php

use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LocaleController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\EventCalendarController;
use App\Http\Controllers\EventCommunicationController;
use App\Http\Controllers\EventFileController;
use App\Http\Controllers\EventReportController;
use App\Http\Controllers\Portal\AiAssistantController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\EventController;
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
        Route::get('/app/events', [EventController::class, 'index'])->name('events.index');
        Route::get('/app/calendar', EventCalendarController::class)->name('events.calendar');
        Route::get('/app/events/{event}', [EventController::class, 'show'])->name('events.show');
        Route::get('/app/events/{event}/communications', [EventCommunicationController::class, 'index'])->name('events.communications.index');
        Route::get('/app/events/{event}/communications/generations/{generation}', [EventCommunicationController::class, 'status'])->middleware('throttle:60,1')->name('events.communications.status');
        Route::middleware(['recent-auth', 'throttle:20,1'])->group(function () {
            Route::post('/app/events/{event}/communications', [EventCommunicationController::class, 'store'])->name('events.communications.store');
            Route::post('/app/events/{event}/communications/generate', [EventCommunicationController::class, 'generate'])->name('events.communications.generate');
            Route::post('/app/events/{event}/communications/generations/{generation}/apply', [EventCommunicationController::class, 'apply'])->name('events.communications.apply');
            Route::delete('/app/events/{event}/communications/{communication}', [EventCommunicationController::class, 'archive'])->name('events.communications.archive');
        });
        Route::get('/app/events/{event}/reports', [EventReportController::class, 'index'])->name('events.reports.index');
        Route::get('/app/events/{event}/reports/{report}/versions/{version}/download', [EventReportController::class, 'download'])->name('events.reports.download');
        Route::post('/app/events/{event}/reports', [EventReportController::class, 'store'])->middleware(['recent-auth', 'throttle:20,1'])->name('events.reports.store');
        Route::delete('/app/events/{event}/reports/{report}', [EventReportController::class, 'destroy'])->middleware(['recent-auth', 'throttle:20,1'])->name('events.reports.destroy');
        Route::get('/app/events/{event}/files', [EventFileController::class, 'index'])->name('events.files.index');
        Route::get('/app/events/{event}/files/{file}/download', [EventFileController::class, 'download'])->name('events.files.download');
        Route::get('/app/events/{event}/files/{file}/preview', [EventFileController::class, 'preview'])->name('events.files.preview');
        Route::middleware(['recent-auth', 'throttle:20,1'])->group(function () {
            Route::post('/app/events/{event}/files', [EventFileController::class, 'store'])->name('events.files.store');
            Route::patch('/app/events/{event}/files/{file}', [EventFileController::class, 'update'])->name('events.files.update');
            Route::delete('/app/events/{event}/files/{file}', [EventFileController::class, 'destroy'])->name('events.files.destroy');
        });
        Route::middleware('permission:use-ai')->group(function () {
            Route::get('/app/assistant', [AiAssistantController::class, 'index'])->name('ai.index');
            Route::get('/app/assistant/new', [AiAssistantController::class, 'create'])->name('ai.create');
            Route::post('/app/assistant', [AiAssistantController::class, 'store'])->middleware('throttle:10,1')->name('ai.store');
            Route::get('/app/assistant/{conversation}', [AiAssistantController::class, 'show'])->name('ai.show');
            Route::post('/app/assistant/{conversation}/messages', [AiAssistantController::class, 'send'])->middleware('throttle:10,1')->name('ai.send');
            Route::get('/app/assistant/{conversation}/requests/{aiRequest}', [AiAssistantController::class, 'status'])->middleware('throttle:60,1')->name('ai.status');
            Route::patch('/app/assistant/{conversation}', [AiAssistantController::class, 'rename'])->name('ai.rename');
            Route::patch('/app/assistant/{conversation}/archive', [AiAssistantController::class, 'archive'])->name('ai.archive');
            Route::delete('/app/assistant/{conversation}', [AiAssistantController::class, 'destroy'])->name('ai.destroy');
            Route::post('/app/assistant/{conversation}/requests/{aiRequest}/cancel', [AiAssistantController::class, 'cancel'])->name('ai.cancel');
            Route::post('/app/assistant/{conversation}/requests/{aiRequest}/retry', [AiAssistantController::class, 'retry'])->middleware('throttle:10,1')->name('ai.retry');
        });
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
