<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Identity\CompleteSignIn;
use App\Actions\Identity\RegisterUser;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;

class RegistrationController
{
    public function store(Request $request, RegisterUser $register, CompleteSignIn $signIn): mixed
    {
        $user = $register->handle($request->all());
        event(new Registered($user));

        return $signIn->handle($user, $request, 'registration');
    }
}
