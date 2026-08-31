<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Locale;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController
{
    public function __invoke(Request $request): mixed
    {
        $data = $request->validate(['locale' => ['required', Rule::enum(Locale::class)]]);
        $request->session()->put('locale', $data['locale']);
        // Only return to same-origin paths; do not trust a posted redirect URL.
        $previous = url()->previous();

        return redirect(str_starts_with($previous, url('/').'/') || $previous === url('/') ? $previous : route('login'));
    }
}
