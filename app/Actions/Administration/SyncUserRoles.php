<?php

namespace App\Actions\Administration;

use App\Actions\Identity\RecordAccountAudit;
use App\Actions\Identity\RevokeCredentials;
use App\Models\User;
use App\Services\Authorization\RoleDelegation;
use App\Services\Authorization\SuperAdministratorGuard;
use App\Support\Authorization\Access;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class SyncUserRoles
{
    public function handle(User $actor, User $user, array $roleIds): void
    {
        Gate::forUser($actor)->authorize('assignRoles', $user);
        $roleIds = collect($roleIds)->map(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))->filter()->unique()->values();
        $roles = Role::where('guard_name', 'web')->whereIn('id', $roleIds)->with('permissions')->get();
        if ($roles->count() !== $roleIds->count()) {
            throw ValidationException::withMessages(['roles' => __('admin.invalid_roles')]);
        }
        $assignable = app(RoleDelegation::class)->assignableRoles($actor)->pluck('id');
        if ($roles->pluck('id')->diff($assignable)->isNotEmpty() || $user->roles()->pluck('roles.id')->diff($assignable)->isNotEmpty()) {
            throw ValidationException::withMessages(['roles' => __('admin.role_escalation')]);
        }

        DB::transaction(function () use ($actor, $user, $roles) {
            $guard = app(SuperAdministratorGuard::class);
            $guard->lockGovernance();
            $user = User::lockForUpdate()->findOrFail($user->id);
            if ($user->hasRole(Access::SUPER_ADMINISTRATOR) && ! $roles->contains('name', Access::SUPER_ADMINISTRATOR)) {
                $guard->assertCanRemoveActiveSuper($user);
            }
            $before = $user->roles()->pluck('name')->sort()->values()->all();
            $user->syncRoles($roles);
            app(RevokeCredentials::class)->handle($user);
            app(RecordAccountAudit::class)->handle($user, 'account.roles_changed', [
                'before' => $before, 'after' => $roles->pluck('name')->sort()->values()->all(),
            ], $actor);
        });
    }
}
