<?php

namespace App\Actions\Social;

use App\Actions\Audit\RecordAudit;
use App\Models\SocialAudienceMetric;
use App\Models\SocialCampaign;
use App\Models\SocialMediaAccount;
use App\Models\SocialPlatform;
use App\Models\SocialTag;
use App\Models\SocialTarget;
use App\Models\User;
use App\Services\Social\SocialAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageSocialCatalog
{
    public const TYPES = [
        'campaign' => [SocialCampaign::class, 'campaigns.manage'], 'tag' => [SocialTag::class, 'campaigns.manage'],
        'target' => [SocialTarget::class, 'targets.manage'], 'audience' => [SocialAudienceMetric::class, 'metrics.manage'],
    ];

    public function account(User $actor, array $input, ?SocialMediaAccount $account = null): SocialMediaAccount
    {
        app(SocialAccess::class)->authorize($actor, 'settings.manage');
        $v = Validator::make($input, [
            'name' => ['required', 'string', 'max:160'], 'identifier' => ['required', 'string', 'max:160', Rule::unique('social_media_accounts')->where('platform_id', SocialPlatform::where('slug', 'linkedin')->value('id'))->ignore($account?->id)],
            'url' => ['nullable', 'url:https', 'max:500'], 'timezone' => ['required', 'timezone'], 'active' => ['required', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($actor, $account, $v) {
            if ($account) {
                $account = SocialMediaAccount::lockForUpdate()->findOrFail($account->id);
                $account->update($v + ['updated_by' => $actor->id]);
            } else {
                $account = SocialMediaAccount::create($v + ['platform_id' => SocialPlatform::where('slug', 'linkedin')->firstOrFail()->id, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            }
            app(RecordAudit::class)->handle($actor->id, 'social.account.saved', 'social', $account->id);

            return $account;
        });
    }

    public function save(User $actor, SocialMediaAccount $account, string $kind, array $input, ?int $id = null): object
    {
        abort_unless(isset(self::TYPES[$kind]), 404);
        [$class,$ability] = self::TYPES[$kind];
        app(SocialAccess::class)->authorize($actor, $ability, $account);
        $rules = match ($kind) {
            'campaign' => ['name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:5000'], 'kind' => ['required', 'in:campaign,initiative'], 'starts_on' => ['nullable', 'date_format:Y-m-d'], 'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'], 'active' => ['required', 'boolean']],
            'tag' => ['name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:5000'], 'active' => ['required', 'boolean']],
            'target' => ['year' => ['required', 'integer', 'between:2000,2100'], 'month' => ['required', 'integer', 'between:0,12'], 'metric' => ['required', 'in:followers,new_followers,growth_rate,impressions,engagement_rate,engagements,clicks,posts'], 'value' => ['required', 'numeric', 'gt:0', 'max:1000000000000'], 'active' => ['required', 'boolean']],
            'audience' => ['metric_date' => ['required', 'date_format:Y-m-d'], 'dimension' => ['required', 'in:location,industry,job_function,seniority'], 'value' => ['required', 'string', 'max:160'], 'audience_count' => ['nullable', 'integer', 'min:0', 'max:1000000000000'], 'percentage' => ['nullable', 'numeric', 'between:0,100']],
        };
        $v = Validator::make($input, $rules)->validate();

        return DB::transaction(function () use ($actor, $account, $kind, $id, $class, $v) {
            SocialMediaAccount::lockForUpdate()->findOrFail($account->id);
            if ($kind === 'target') {
                $duplicate = $class::where('social_account_id', $account->id)->where('year', $v['year'])->where('month', $v['month'])->where('metric', $v['metric'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['metric' => __('social.duplicate')]);
                }
            }
            if ($kind === 'audience') {
                $duplicate = $class::where('social_account_id', $account->id)->where('metric_date', $v['metric_date'])->where('dimension', $v['dimension'])->where('value', $v['value'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['value' => __('social.duplicate')]);
                }
            }
            $values = $v + ($kind === 'audience' ? [] : ['updated_by' => $actor->id]);
            if ($id) {
                $row = $class::where('social_account_id', $account->id)->findOrFail($id);
                $row->update($values);
            } else {
                $row = $class::create($values + ['social_account_id' => $account->id, 'created_by' => $actor->id]);
            }
            app(RecordAudit::class)->handle($actor->id, 'social.'.$kind.'.saved', 'social', $account->id);

            return $row;
        });
    }

    public function delete(User $actor, SocialMediaAccount $account, string $kind, int $id): void
    {
        abort_unless(isset(self::TYPES[$kind]) && $kind !== 'audience', 404);
        [$class,$ability] = self::TYPES[$kind];
        app(SocialAccess::class)->authorize($actor, $ability, $account);
        DB::transaction(function () use ($actor, $account, $class, $id, $kind) {
            SocialMediaAccount::lockForUpdate()->findOrFail($account->id);
            $row = $class::where('social_account_id', $account->id)->findOrFail($id);
            $row->delete();
            app(RecordAudit::class)->handle($actor->id, 'social.'.$kind.'.deleted', 'social', $account->id);
        });
    }

    public function settings(User $actor, SocialMediaAccount $account, array $input): void
    {
        app(SocialAccess::class)->authorize($actor, 'settings.manage', $account);
        $rules = ['engagement_components' => ['required', 'array', 'min:1', 'max:4'], 'engagement_components.*' => ['required', 'distinct', 'in:reactions,comments,shares,clicks'],
            'attention_ratio' => ['required', 'numeric', 'between:0,1']];
        foreach (array_keys(config('social.score_weights')) as $key) {
            $rules['score_weights.'.$key] = ['required', 'numeric', 'between:0,100'];
        }
        foreach (array_keys(config('social.score_thresholds')) as $key) {
            $rules['score_thresholds.'.$key] = ['required', 'numeric', 'between:0,100'];
        }
        $v = Validator::make($input, $rules)->validate();
        if (abs(array_sum($v['score_weights']) - 100) > 0.001) {
            throw ValidationException::withMessages(['score_weights' => __('social.weights_total')]);
        }
        $thresholds = array_values($v['score_thresholds']);
        if ($thresholds !== collect($thresholds)->sortDesc()->values()->all() || end($thresholds) != 0) {
            throw ValidationException::withMessages(['score_thresholds' => __('social.threshold_order')]);
        }
        DB::transaction(function () use ($actor, $account, $v) {
            $account = SocialMediaAccount::lockForUpdate()->findOrFail($account->id);
            $account->update(['settings' => $v, 'revision' => $account->revision + 1, 'updated_by' => $actor->id]);
            app(RecordAudit::class)->handle($actor->id, 'social.settings.saved', 'social', $account->id, ['revision' => $account->revision]);
        });
    }
}
