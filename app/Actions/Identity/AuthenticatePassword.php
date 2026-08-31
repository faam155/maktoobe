<?php

namespace App\Actions\Identity;

use App\Models\User;
use App\Support\Identity\Identifiers;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticatePassword
{
    public function handle(Request $request, Closure $next): mixed
    {
        $request->validate(['login' => 'required|string|max:254', 'password' => 'required|string|max:128', 'remember' => 'sometimes|boolean']);
        $identifier = Identifiers::canonical($request->input('login'));
        $user = User::where(str_contains($identifier, '@') ? 'email' : 'username', $identifier)->first();
        // A valid bcrypt value preserves password work for unknown/passwordless accounts.
        $hash = $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if (! Hash::check((string) $request->input('password'), $hash) || ! $user || ! $user->password) {
            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        return app(CompleteSignIn::class)->handle($user, $request, 'password', $request->boolean('remember'));
    }
}
