<?php

namespace App\Actions\Social;

use App\Actions\Audit\RecordAudit;
use App\Jobs\GenerateSocialInsightJob;
use App\Models\SocialAiInsight;
use App\Models\SocialMediaAccount;
use App\Models\User;
use App\Queries\Social\SocialAnalyticsQuery;
use App\Services\Ai\AiModelAccess;
use App\Services\Social\SocialAccess;
use App\Services\Social\SocialProvenance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class GenerateSocialInsight
{
    public function handle(User $actor, SocialMediaAccount $account, array $filters, array $input): SocialAiInsight
    {
        app(SocialAccess::class)->authorize($actor, 'ai.generate', $account);
        app(SocialAccess::class)->authorize($actor, 'ai.view', $account);
        Gate::forUser($actor)->authorize('use-ai');
        $v = Validator::make($input, ['operation_id' => ['required', 'uuid'], 'model' => ['nullable', 'string', 'max:100']])->validate();
        $model = app(AiModelAccess::class)->authorize($actor, $v['model'] ?? null);

        return DB::transaction(function () use ($actor, $account, $filters, $v, $model) {
            User::lockForUpdate()->findOrFail($actor->id);
            SocialMediaAccount::lockForUpdate()->findOrFail($account->id);
            $existing = SocialAiInsight::where('operation_id', $v['operation_id'])->first();
            if ($existing) {
                abort_unless($existing->user_id === $actor->id && $existing->social_account_id === $account->id, 409);

                return $existing;
            }
            $reserved = (int) config('ai.max_output_tokens');
            abort_if(SocialAiInsight::where('user_id', $actor->id)->where('created_at', '>=', now()->startOfDay())->sum('reserved_tokens') + $reserved > config('social.daily_output_token_budget'), 429);
            abort_if(SocialAiInsight::where('user_id', $actor->id)->whereIn('status', ['queued', 'processing'])->exists(), 429);
            $data = app(SocialAnalyticsQuery::class)->get($actor, $account, $filters);
            $snapshot = ['metrics' => $data['metrics'], 'changes' => $data['changes'], 'groups' => collect($data['groups'])->map(fn ($rows) => $rows->take(20)->values()->all())->all(),
                'tags' => $data['tags']->take(20)->all(), 'trend' => array_slice($data['trend'], 0, 367), 'publishing' => $data['publishing'],
                'visibility' => app(SocialProvenance::class)->fingerprint($actor, $account), 'locale' => app()->getLocale()];
            $row = SocialAiInsight::create(['social_account_id' => $account->id, 'user_id' => $actor->id, 'operation_id' => $v['operation_id'], 'status' => 'queued', 'model' => $model,
                'filters' => $filters, 'settings' => ['max_output_tokens' => config('ai.max_output_tokens'), 'temperature' => config('ai.temperature')], 'snapshot' => $snapshot, 'reserved_tokens' => $reserved]);
            app(RecordAudit::class)->handle($actor->id, 'social.ai.requested', 'social', $account->id);
            GenerateSocialInsightJob::dispatch($row->id)->afterCommit();

            return $row;
        });
    }
}
