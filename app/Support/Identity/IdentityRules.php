<?php

namespace App\Support\Identity;

use Illuminate\Validation\Rules\Password;

class IdentityRules
{
    public static function password(): array
    {
        return ['required', 'string', 'max:72', Password::min(12)->letters()->numbers(), 'confirmed', function ($attribute, $value, $fail) {
            if (is_string($value) && strlen($value) > 72) {
                $fail(__('auth.password_bytes'));
            }
        }];
    }

    public static function phone(): array
    {
        return ['nullable', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'];
    }
}
