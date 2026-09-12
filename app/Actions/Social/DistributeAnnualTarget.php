<?php

namespace App\Actions\Social;

use App\Actions\Audit\RecordAudit;
use App\Models\SocialMediaAccount;
use App\Models\SocialTarget;
use App\Models\User;
use App\Services\Social\SocialAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistributeAnnualTarget
{
    public function handle(User $actor, SocialMediaAccount $account, SocialTarget $target): void
    {
        app(SocialAccess::class)->authorize($actor, 'targets.manage', $account);
        DB::transaction(function () use ($actor, $account, $target) {
            SocialMediaAccount::lockForUpdate()->findOrFail($account->id);
            $target = SocialTarget::lockForUpdate()->findOrFail($target->id);
            abort_unless($target->social_account_id === $account->id && $target->month === 0, 404);
            if (! in_array($target->metric, ['new_followers', 'impressions', 'engagements', 'clicks', 'posts'], true) || $target->value < 12 ||
                SocialTarget::where('social_account_id', $account->id)->where('year', $target->year)->where('metric', $target->metric)->where('month', '>', 0)->exists()) {
                throw ValidationException::withMessages(['metric' => __('social.distribution_conflict')]);
            }
            $base = floor($target->value / 12);
            $remainder = $target->value - $base * 12;
            for ($month = 1; $month <= 12; $month++) {
                SocialTarget::create(['social_account_id' => $account->id, 'year' => $target->year, 'month' => $month, 'metric' => $target->metric, 'value' => $base + ($month === 12 ? $remainder : 0), 'active' => $target->active, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            }
            app(RecordAudit::class)->handle($actor->id, 'social.target.distributed', 'social', $account->id);
        });
    }
}
