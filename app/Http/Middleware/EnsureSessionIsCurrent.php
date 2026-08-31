<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureSessionIsCurrent
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($user = $request->user()) {
            // Long-lived workers and repeated requests must use current persisted state.
            $user = User::find($user->getAuthIdentifier());
            $version = $request->session()->get('auth.security_version');
            if ($user && $version === null && Auth::viaRemember() && $user->status !== AccountStatus::Disabled) {
                $request->session()->put('auth.security_version', $user->security_version);
                $version = $user->security_version;
            }
            if (! $user || $user->status === AccountStatus::Disabled || $version !== $user->security_version) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['login' => __('auth.session_expired')]);
            }
            Auth::guard()->setUser($user);
        }

        return $next($request);
    }
}
