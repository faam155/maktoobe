<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class VerificationController
{
    public function show(Request $request): mixed
    {
        return $request->user()->hasVerifiedEmail() ? redirect()->route('account.home') : view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): mixed
    {
        $request->fulfill();

        return redirect()->route('account.home')->with('status', __('auth.email_verified'));
    }

    public function resend(Request $request): mixed
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', __('auth.verification_sent'));
    }
}
