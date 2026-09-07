<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $preference = $request->session()->get('locale')
            ?? $request->user()?->locale
            ?? config('app.locale');

        app()->setLocale(Locale::resolve($preference)->value);

        return $next($request);
    }
}
