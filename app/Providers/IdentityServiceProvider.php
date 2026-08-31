<?php

namespace App\Providers;

use App\Actions\Identity\AuthenticatePassword;
use App\Actions\Identity\ResetPassword;
use App\Contracts\SmsGateway;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureRecentAuthentication;
use App\Services\Identity\DisabledSmsGateway;
use App\Services\Identity\LocalSmsGateway;
use App\Support\Identity\Identifiers;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
        $this->app->bind(SmsGateway::class, fn () => config('identity.sms_driver') === 'local' ? new LocalSmsGateway : new DisabledSmsGateway);
    }

    public function boot(): void
    {
        Fortify::authenticateThrough(fn () => [AuthenticatePassword::class]);
        Fortify::resetUserPasswordsUsing(ResetPassword::class);
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Livewire::addPersistentMiddleware([EnsureAccountIsActive::class, EnsureRecentAuthentication::class]);
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by('login:'.Identifiers::digest(Identifiers::canonical($r->input('login')).'|'.$r->ip())),
            Limit::perMinute(30)->by('login-ip:'.$r->ip()),
        ]);
        RateLimiter::for('registration', fn (Request $r) => Limit::perHour(10)->by($r->ip()));
        RateLimiter::for('recovery', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('otp-request', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('otp-verify', fn (Request $r) => Limit::perMinute(15)->by($r->ip()));
    }
}
