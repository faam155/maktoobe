<?php

namespace App\Data;

use App\Models\User;
use Illuminate\Http\Request;

class SignInAttempt
{
    public function __construct(public User $user, public Request $request, public string $method, public bool $remember = false) {}
}
