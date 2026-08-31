<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRecentAuthentication
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ((int) $request->session()->get('auth.confirmed_at', 0) < time() - config('identity.recent_auth_seconds')) {
            return redirect()->route('password.confirm');
        }

        return $next($request);
    }
}
