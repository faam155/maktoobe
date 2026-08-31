<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Identity\CompleteSignIn;
use App\Actions\Identity\ResolveGoogleIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController
{
    public static function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret')) && filled(config('services.google.redirect'));
    }

    public function redirect(Request $request): mixed
    {
        if (! self::configured()) {
            return redirect()->route('login')->withErrors(['google' => __('auth.google_unavailable')]);
        }
        $request->session()->forget('google.link_user');
        $request->session()->put('google.started_at', time());

        return Socialite::driver('google')->redirect();
    }

    public function link(Request $request): mixed
    {
        Gate::authorize('manageSecurity', $request->user());
        if (! self::configured()) {
            return back()->withErrors(['google' => __('auth.google_unavailable')]);
        }
        $request->session()->put(['google.link_user' => $request->user()->id, 'google.started_at' => time()]);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, ResolveGoogleIdentity $resolve, CompleteSignIn $signIn): mixed
    {
        $actorId = $request->session()->pull('google.link_user');
        $started = $request->session()->pull('google.started_at', 0);
        if (! self::configured() || $started < time() - 600) {
            return redirect()->route('login')->withErrors(['google' => __('auth.google_failed')]);
        }
        try {
            // Socialite validates and consumes OAuth state. Never use stateless().
            $identity = Socialite::driver('google')->user();
            $actor = null;
            if ($actorId !== null) {
                if (! $request->user() || $request->user()->id !== $actorId || $request->session()->get('auth.confirmed_at', 0) < time() - config('identity.recent_auth_seconds')) {
                    abort(403);
                }
                $actor = $request->user();
            } elseif ($request->user()) {
                abort(403);
            }
            $user = $resolve->handle($identity, $actor);

            return $actor ? redirect()->route('account.security')->with('status', __('auth.google_linked')) : $signIn->handle($user, $request, 'google');
        } catch (ValidationException $exception) {
            return redirect()->route($actorId ? 'account.security' : 'login')->withErrors($exception->errors());
        } catch (Throwable) {
            // Provider exceptions can contain tokens; return a safe error without logging their body.
            return redirect()->route('login')->withErrors(['google' => __('auth.google_failed')]);
        }
    }
}
