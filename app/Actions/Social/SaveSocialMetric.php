<?php

namespace App\Actions\Social;

use App\Actions\Audit\RecordAudit;
use App\Models\SocialAccountMetric;
use App\Models\SocialMediaAccount;
use App\Models\SocialPost;
use App\Models\SocialPostMetric;
use App\Models\User;
use App\Services\Social\SocialAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveSocialMetric
{
    public function validate(array $input): array
    {
        $rules = ['subject' => ['required', 'in:account,post'], 'post_id' => ['nullable', 'required_if:subject,post', 'integer', 'min:1'],
            'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on']];
        foreach (['impressions', 'reactions', 'comments', 'shares', 'clicks', 'followers', 'new_followers'] as $key) {
            $rules[$key] = ['nullable', 'integer', 'min:0', 'max:1000000000000'];
        }
        $v = Validator::make($input, $rules)->validate();
        $s = CarbonImmutable::parse($v['starts_on']);
        $e = CarbonImmutable::parse($v['ends_on']);
        if (! $s->isSameDay($e) && ! ($s->day === 1 && $e->isSameDay($s->endOfMonth()))) {
            throw ValidationException::withMessages(['ends_on' => __('social.daily_monthly')]);
        }
        if (! collect($v)->only(['impressions', 'reactions', 'comments', 'shares', 'clicks', 'followers', 'new_followers'])->contains(fn ($n) => $n !== null)) {
            throw ValidationException::withMessages(['impressions' => __('social.metric_required')]);
        }

        return $v;
    }

    public function handle(User $actor, SocialMediaAccount $account, array $input, string $source = 'manual'): object
    {
        $ability = match ($source) {
            'api' => 'sync','import' => 'import','manual' => 'metrics.manage',default => throw new \InvalidArgumentException('Unknown metric source.')
        };
        app(SocialAccess::class)->authorize($actor, $ability, $account);
        $v = $this->validate($input);

        return DB::transaction(function () use ($actor, $account, $v, $source, $ability) {
            $account = SocialMediaAccount::lockForUpdate()->findOrFail($account->id);
            app(SocialAccess::class)->authorize($actor, $ability, $account);
            $subject = $v['subject'];
            if ($subject === 'post') {
                $post = SocialPost::findOrFail($v['post_id']);
                abort_unless($post->social_account_id === $account->id, 404);
                app(SocialAccess::class)->post($actor, $post, $ability);
            }
            $class = $subject === 'post' ? SocialPostMetric::class : SocialAccountMetric::class;
            $fk = 'social_'.$subject.'_id';
            $id = $subject === 'post' ? $post->id : $account->id;
            $overlap = $class::where($fk, $id)->where('starts_on', '<=', $v['ends_on'])->where('ends_on', '>=', $v['starts_on'])->lockForUpdate()->get();
            $existing = $overlap->first(fn ($r) => $r->starts_on === $v['starts_on'] && $r->ends_on === $v['ends_on']);
            if ($overlap->count() > ($existing ? 1 : 0)) {
                throw ValidationException::withMessages(['starts_on' => __('social.overlap')]);
            }
            $values = collect($v)->except(['subject', 'post_id'])->all();
            if ($subject === 'post') {
                unset($values['followers'],$values['new_followers']);
            }
            if ($existing) {
                DB::table('social_metric_revisions')->insert([$subject.'_metric_id' => $existing->id, 'values' => json_encode($existing->getAttributes(), JSON_THROW_ON_ERROR), 'actor_id' => $actor->id, 'created_at' => now()]);
                $existing->update($values + ['source' => $source, 'revision' => $existing->revision + 1, 'updated_by' => $actor->id]);
                $row = $existing;
            } else {
                $row = $class::create($values + [$fk => $id, 'source' => $source, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            }
            app(RecordAudit::class)->handle($actor->id, 'social.metrics.saved', 'social', $account->id, ['revision' => $row->revision]);

            return $row;
        });
    }
}
