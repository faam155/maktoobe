<?php

namespace App\Actions\Identity;

use App\Actions\Audit\RecordAudit;
use App\Models\User;
use App\Services\Audit\SafeAuditMetadata;
use Illuminate\Support\Facades\DB;

class RecordAccountAudit
{
    public function handle(User $user, string $action, array $metadata = [], ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $action, $metadata, $actor) {
            $metadata = app(SafeAuditMetadata::class)->clean($metadata);
            $id = DB::table('account_audits')->insertGetId([
                'user_id' => $user->id, 'actor_id' => $actor?->id, 'action' => $action,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);
            app(RecordAudit::class)->account(DB::table('account_audits')->find($id));
        });
    }
}
