<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Support\Identity\Identifiers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordRecoveryController
{
    public function store(Request $request): mixed
    {
        $email = Identifiers::canonical($request->input('email'));
        validator(['email' => $email], ['email' => 'required|string|email:rfc|max:254'])->validate();
        $user = User::where('email', $email)->where('status', '!=', AccountStatus::Disabled)->first();
        if ($user) {
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with('status', __('auth.reset_sent'));
    }
}
