<?php

namespace App\Jobs;

use App\Actions\Social\SaveSocialMetric;
use App\Contracts\SocialMediaProvider;
use App\Models\SocialMediaAccount;
use App\Models\SocialSyncLog;
use App\Models\User;
use App\Services\Social\SocialAccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncSocialAccount implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $logId)
    {
        $this->onQueue('social');
    }

    public function handle(SocialMediaProvider $provider): void
    {
        $log = SocialSyncLog::find($this->logId);
        if (! $log || $log->status !== 'queued') {
            return;
        }
        if (! SocialSyncLog::whereKey($log->id)->where('status', 'queued')->update(['status' => 'processing'])) {
            return;
        }
        try {
            $actor = User::findOrFail($log->user_id);
            $account = SocialMediaAccount::findOrFail($log->social_account_id);
            app(SocialAccess::class)->authorize($actor, 'sync', $account);
            $created = 0;
            $updated = 0;
            foreach ($provider->fetch($account) as $input) {
                $actor->refresh();
                app(SocialAccess::class)->authorize($actor, 'sync', $account->fresh());
                $row = app(SaveSocialMetric::class)->handle($actor, $account, $input, 'api');
                $row->wasRecentlyCreated ? $created++ : $updated++;
                $log->update(['processed' => $created + $updated, 'created' => $created, 'updated' => $updated]);
            }
            DB::transaction(function () use ($account, $log) {
                $log->update(['status' => 'successful', 'completed_at' => now()]);
                $account->update(['last_sync_at' => now()]);
            });
        } catch (\Throwable) {
            $log->refresh()->update(['status' => $log->processed ? 'partial' : 'failed', 'error_code' => 'sync_unavailable', 'completed_at' => now()]);
            Log::warning('social.sync.failed', ['sync_id' => $log->id]);
        }
    }

    public function failed(?\Throwable $e): void
    {
        SocialSyncLog::whereKey($this->logId)->whereIn('status', ['queued', 'processing'])->update(['status' => 'failed', 'error_code' => 'interrupted', 'completed_at' => now()]);
    }
}
