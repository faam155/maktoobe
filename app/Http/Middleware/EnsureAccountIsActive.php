<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use Closure;
use Illuminate\Http\Request;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): mixed
    {
        return $request->user()?->status === AccountStatus::Active ? $next($request) : redirect()->route('account.pending');
    }
}
