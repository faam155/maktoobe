<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Identity\CompleteSignIn;
use App\Actions\Identity\IssueOtp;
use App\Actions\Identity\VerifyOtp;
use App\Support\Identity\Identifiers;
use App\Support\Identity\IdentityRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OtpController
{
    public function request(Request $request, IssueOtp $issue): mixed
    {
        $phone = Identifiers::phone($request->input('phone'));
        validator(['phone' => $phone], ['phone' => ['required', ...IdentityRules::phone()]])->validate();
        $challenge = $issue->handle($phone, $request->session()->getId());
        $request->session()->put('otp.login', $challenge->id);

        return redirect()->route('otp.verify')->with('status', __('auth.otp_sent'));
    }

    public function show(Request $request): mixed
    {
        return $request->session()->has('otp.login') ? view('auth.otp-verify', ['purpose' => 'login']) : redirect()->route('otp.request');
    }

    public function verify(Request $request, VerifyOtp $verify, CompleteSignIn $signIn): mixed
    {
        $data = $request->validate(['code' => 'required|string|digits:6']);
        $user = $verify->handle((string) $request->session()->get('otp.login'), $data['code'], $request->session()->getId(), 'login');
        if (! $user) {
            throw ValidationException::withMessages(['code' => __('auth.otp_invalid')]);
        }
        $request->session()->forget('otp.login');

        return $signIn->handle($user, $request, 'otp');
    }

    public function enroll(Request $request, IssueOtp $issue): mixed
    {
        Gate::authorize('manageSecurity', $request->user());
        if (! $request->user()->phone_e164) {
            return back()->withErrors(['phone' => __('auth.no_phone')]);
        }
        $challenge = $issue->handle($request->user()->phone_e164, $request->session()->getId(), 'enroll', $request->user());
        $request->session()->put('otp.enroll', $challenge->id);

        return redirect()->route('phone.verify')->with('status', __('auth.otp_sent'));
    }

    public function showEnrollment(Request $request): mixed
    {
        return $request->session()->has('otp.enroll') ? view('auth.otp-verify', ['purpose' => 'enroll']) : redirect()->route('account.security');
    }

    public function verifyEnrollment(Request $request, VerifyOtp $verify): mixed
    {
        Gate::authorize('manageSecurity', $request->user());
        $data = $request->validate(['code' => 'required|string|digits:6']);
        $user = $verify->handle((string) $request->session()->get('otp.enroll'), $data['code'], $request->session()->getId(), 'enroll', $request->user());
        if (! $user) {
            throw ValidationException::withMessages(['code' => __('auth.otp_invalid')]);
        }
        $request->session()->forget('otp.enroll');

        return redirect()->route('account.security')->with('status', __('auth.phone_verified'));
    }
}
