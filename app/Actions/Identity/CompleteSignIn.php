<?php

namespace App\Actions\Identity;

use App\Data\SignInAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompleteSignIn
{
    public function handle(User $user, Request $request, string $method, bool $remember = false): mixed
    {
        return DB::transaction(function () use ($user, $request, $method, $remember) {
            $fresh = User::withTrashed()->lockForUpdate()->findOrFail($user->id);

            return app(Pipeline::class)->send(new SignInAttempt($fresh, $request, $method, $remember))
                ->through([EnsureSignInAllowed::class, ...config('identity.additional_sign_in_checks', [])])
                ->then(function (SignInAttempt $attempt) {
                    Auth::guard('web')->login($attempt->user, $attempt->remember);
                    $attempt->request->session()->regenerate();
                    $attempt->request->session()->put([
                        'auth.security_version' => $attempt->user->security_version,
                        'auth.confirmed_at' => time(), 'auth.method' => $attempt->method,
                    ]);

                    return redirect()->route('account.home');
                });
        });
    }
}
