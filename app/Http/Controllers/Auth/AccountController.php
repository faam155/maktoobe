<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Identity\RecordAccountAudit;
use App\Actions\Identity\RevokeCredentials;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController
{
    public function security(Request $request): mixed
    {
        Gate::authorize('manageSecurity', $request->user());
        $sessions = DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->limit(100)->get()
            ->map(fn ($session) => ['current' => $session->id === $request->session()->getId(), 'ip' => $session->ip_address, 'agent' => $session->user_agent, 'last_activity' => $session->last_activity]);

        return view('auth.security', ['sessions' => $sessions]);
    }

    public function confirm(Request $request): mixed
    {
        $request->validate(['password' => 'required|string|max:128']);
        if (! $request->user()->password || ! Hash::check($request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => __('auth.failed')]);
        }
        $request->session()->put('auth.confirmed_at', time());

        return redirect()->route('account.security');
    }

    public function revokeOthers(Request $request): mixed
    {
        Gate::authorize('manageSecurity', $request->user());
        DB::transaction(function () use ($request) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            app(RevokeCredentials::class)->handle($user, $request->session()->getId());
            $request->session()->put('auth.security_version', $user->security_version);
            app(RecordAccountAudit::class)->handle($user, 'sessions.revoked', actor: $user);
        });

        return back()->with('status', __('auth.sessions_revoked'));
    }
}
