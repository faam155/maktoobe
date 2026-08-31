<?php

namespace App\Actions\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordAccountAudit
{
    public function handle(User $user, string $action, array $metadata = [], ?User $actor = null): void
    {
        DB::table('account_audits')->insert([
            'user_id' => $user->id, 'actor_id' => $actor?->id, 'action' => $action,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
