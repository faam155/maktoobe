<?php

namespace App\Actions\Social;

use App\Actions\Audit\RecordAudit;
use App\Models\SocialMediaAccount;
use App\Models\SocialReport;
use App\Models\User;
use App\Services\Social\SocialAccess;
use App\Services\Social\SocialProvenance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SaveSocialReport
{
    public static function rules(): array
    {
        return ['name' => ['required', 'string', 'max:160'], 'summary' => ['nullable', 'string', 'max:10000']];
    }

    public function handle(User $actor, SocialMediaAccount $account, array $input, array $data): SocialReport
    {
        app(SocialAccess::class)->authorize($actor, 'export', $account);
        $v = Validator::make($input, self::rules())->validate();

        return DB::transaction(function () use ($actor, $account, $v, $data) {
            $locked = SocialMediaAccount::lockForUpdate()->findOrFail($account->id);
            app(SocialAccess::class)->authorize($actor, 'export', $locked);
            $report = SocialReport::create(['social_account_id' => $locked->id, 'user_id' => $actor->id, 'name' => $v['name'], 'summary' => $v['summary'] ?? $data['summary'],
                'filters' => $data['f'] + ['_visibility' => app(SocialProvenance::class)->fingerprint($actor, $locked)], 'settings' => $data['settings']]);
            app(RecordAudit::class)->handle($actor->id, 'social.report.saved', 'social', $locked->id);

            return $report;
        });
    }
}
