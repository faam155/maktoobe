<?php

namespace App\Actions\Administration;

use App\Actions\Identity\RecordAccountAudit;
use App\Actions\Identity\RevokeCredentials;
use App\Models\User;
use App\Services\Authorization\SuperAdministratorGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteUser
{
    public function handle(User $actor, User $user): void
    {
        Gate::forUser($actor)->authorize('delete', $user);
        DB::transaction(function () use ($actor, $user) {
            $guard = app(SuperAdministratorGuard::class);
            $guard->lockGovernance();
            $user = User::lockForUpdate()->findOrFail($user->id);
            $guard->assertCanRemoveActiveSuper($user);
            app(RevokeCredentials::class)->handle($user);
            app(RecordAccountAudit::class)->handle($user, 'account.deleted_by_admin', actor: $actor);
            $user->delete();
        });
    }
}
