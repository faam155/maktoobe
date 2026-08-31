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

class UpdateRole
{
    public function handle(User $actor, Role $role, array $input): Role
    {
        Gate::forUser($actor)->authorize('update', $role);
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[\pL\pN][\pL\pN ._-]*$/u', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ])->validate();
        $permissionNames = array_values(array_unique($data['permissions'] ?? []));
        $canSyncPermissions = Gate::forUser($actor)->allows('updatePermissions', $role);
        if (array_key_exists('permissions', $input) && ! $canSyncPermissions) {
            abort(403);
        }
        if ($canSyncPermissions) {
            app(RoleDelegation::class)->assertPermissionsDelegable($actor, $permissionNames);
        }

        return DB::transaction(function () use ($actor, $role, $data, $permissionNames, $canSyncPermissions) {
            $role = Role::lockForUpdate()->findOrFail($role->id);
            $before = ['name' => $role->name, 'permissions' => $role->permissions()->pluck('name')->sort()->values()->all()];
            $role->update(['name' => trim(preg_replace('/\s+/u', ' ', $data['name']))]);
            if ($canSyncPermissions) {
                $role->syncPermissions(Permission::whereIn('name', $permissionNames)->get());
            }
            app(RecordAccountAudit::class)->handle($actor, 'role.updated', [
                'role_id' => $role->id, 'before' => $before,
                'after' => ['name' => $role->name, 'permissions' => $role->permissions()->pluck('name')->sort()->values()->all()],
            ], $actor);

            return $role;
        });
    }
}
