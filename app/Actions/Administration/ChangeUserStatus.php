<?php

namespace App\Actions\Administration;

use App\Actions\Identity\SetAccountStatus;
use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ChangeUserStatus
{
    public function handle(User $actor, User $user, array $input): void
    {
        Gate::forUser($actor)->authorize('changeStatus', $user);
        $data = Validator::make($input, [
            'status' => ['required', Rule::in([AccountStatus::Active->value, AccountStatus::Disabled->value])],
            'reason' => ['required', 'string', 'min:8', 'max:200'],
        ])->validate();
        app(SetAccountStatus::class)->handle($user, AccountStatus::from($data['status']), trim($data['reason']), $actor);
    }
}
