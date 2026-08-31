<?php

namespace App\Actions\Administration;

use App\Actions\Identity\RecordAccountAudit;
use App\Models\User;
use App\Support\Authorization\Access;
use App\Support\Identity\Identifiers;
use App\Support\Identity\IdentityRules;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class CreateUser
{
    public function handle(User $actor, array $input): User
    {
        Gate::forUser($actor)->authorize('create', User::class);
        $input['username'] = Identifiers::canonical($input['username'] ?? '');
        $input['email'] = Identifiers::canonical($input['email'] ?? '');
        $input['phone'] = Identifiers::phone($input['phone'] ?? null);
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9][a-z0-9_.-]*$/', 'unique:users,username'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', 'unique:users,email'],
            'phone' => [...IdentityRules::phone(), 'unique:users,phone_e164'],
            'password' => IdentityRules::password(),
            'status' => ['required', Rule::in(['pending', 'active'])],
            'locale' => ['required', Rule::in(['en', 'ar'])],
        ])->validate();

        try {
            return DB::transaction(function () use ($actor, $data) {
                $user = new User;
                $user->forceFill([
                    'name' => trim($data['name']), 'username' => $data['username'], 'email' => $data['email'],
                    'phone_e164' => $data['phone'], 'password' => $data['password'], 'status' => $data['status'],
                    'locale' => $data['locale'], 'timezone' => 'UTC',
                ])->save();
                if ($role = Role::where('name', Access::STANDARD_USER)->where('guard_name', 'web')->first()) {
                    $user->assignRole($role);
                }
                app(RecordAccountAudit::class)->handle($user, 'account.created_by_admin', actor: $actor);

                return $user;
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }
            throw ValidationException::withMessages(['email' => __('auth.identifiers_unavailable')]);
        }
    }
}
