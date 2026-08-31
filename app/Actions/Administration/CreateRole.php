<?php

namespace App\Actions\Administration;

use App\Actions\Identity\RecordAccountAudit;
use App\Models\User;
use App\Services\Authorization\RoleDelegation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateRole
{
    public function handle(User $actor, array $input): Role
    {
        Gate::forUser($actor)->authorize('create', Role::class);
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[\pL\pN][\pL\pN ._-]*$/u', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ])->validate();
        $permissionNames = array_values(array_unique($data['permissions'] ?? []));
        if ($permissionNames !== []) {
            Gate::forUser($actor)->authorize('updatePermissions', new Role(['name' => trim($data['name']), 'guard_name' => 'web']));
            app(RoleDelegation::class)->assertPermissionsDelegable($actor, $permissionNames);
        }

        return DB::transaction(function () use ($actor, $data, $permissionNames) {
            $role = Role::create(['name' => trim(preg_replace('/\s+/u', ' ', $data['name'])), 'guard_name' => 'web']);
            $role->syncPermissions(Permission::whereIn('name', $permissionNames)->get());
            app(RecordAccountAudit::class)->handle($actor, 'role.created', ['role_id' => $role->id, 'role_name' => $role->name], $actor);

            return $role;
        });
    }
}
