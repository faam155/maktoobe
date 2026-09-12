<?php

namespace App\Actions\Social;

use App\Actions\Audit\RecordAudit;
use App\Jobs\SyncSocialAccount;
use App\Models\SocialMediaAccount;
use App\Models\SocialSyncLog;
use App\Models\User;
use App\Services\Social\SocialAccess;
use Illuminate\Support\Facades\DB;

class RefreshSocialAccount
{
    public function handle(User $actor, SocialMediaAccount $account): SocialSyncLog
    {
        app(SocialAccess::class)->authorize($actor, 'sync', $account);

        return DB::transaction(function () use ($actor, $account) {
            SocialMediaAccount::lockForUpdate()->findOrFail($account->id);
            $existing = SocialSyncLog::where('social_account_id', $account->id)->whereIn('status', ['queued', 'processing'])->first();
            if ($existing) {
                return $existing;
            }
            $log = SocialSyncLog::create(['social_account_id' => $account->id, 'user_id' => $actor->id, 'type' => 'api', 'status' => 'queued', 'started_at' => now()]);
            app(RecordAudit::class)->handle($actor->id, 'social.sync.requested', 'social', $account->id);
            SyncSocialAccount::dispatch($log->id)->afterCommit();

            return $log;
        });
    }
}
