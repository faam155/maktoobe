<?php

namespace App\Queries\Social;

use App\Models\Event;
use App\Models\SocialAccountMetric;
use App\Models\SocialAiInsight;
use App\Models\SocialAudienceMetric;
use App\Models\SocialCampaign;
use App\Models\SocialContentType;
use App\Models\SocialMediaAccount;
use App\Models\SocialPostMetric;
use App\Models\SocialTarget;
use App\Models\User;
use App\Services\Events\EventAccess;
use App\Services\Social\SocialAccess;
use App\Services\Social\SocialFilters;
use App\Services\Social\SocialMath;
use App\Services\Social\SocialProvenance;
use Carbon\CarbonImmutable as Date;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SocialAnalyticsQuery
{
    public const COUNTS = ['impressions', 'reactions', 'comments', 'shares', 'clicks'];

    public function settings(SocialMediaAccount $account): array
    {
        return array_replace(config('social'), $account->settings ?? []);
    }

    public function posts(User $actor, SocialMediaAccount $account, array $f): Builder
    {
        $q = app(SocialAccess::class)->posts($actor, $account)->where('status', 'published');
        foreach (['content_type_id', 'campaign_id', 'event_id', 'language'] as $key) {
            if (filled($f[$key] ?? null)) {
                $q->where($key, $f[$key]);
            }
        }

        return $q;
    }

    public function postStats(User $actor, SocialMediaAccount $account, array $f): Builder
    {
        $metric = DB::table('social_post_metrics')->where('starts_on', '>=', $f['from'])->where('ends_on', '<=', $f['to'])
            ->select('social_post_id')->selectRaw('COUNT(*) measured');
        foreach (self::COUNTS as $m) {
            $metric->selectRaw("CASE WHEN COUNT($m)=COUNT(*) THEN SUM($m) ELSE NULL END AS $m");
        }
        $metric->groupBy('social_post_id');
        $q = $this->posts($actor, $account, $f)->leftJoinSub($metric, 'measurements', 'measurements.social_post_id', '=', 'social_posts.id')
            ->where(function ($q) use ($account, $f) {
                $q->whereNotNull('measurements.social_post_id')->orWhereBetween('published_at', [
                    Date::parse($f['from'], $account->timezone)->utc(), Date::parse($f['to'], $account->timezone)->endOfDay()->utc()]);
            })->select('social_posts.*', 'measurements.measured');
        foreach (self::COUNTS as $m) {
            $q->addSelect('measurements.'.$m);
        }
        $components = $this->settings($account)['engagement_components'];
        $expression = implode('+', array_map(fn ($m) => 'measurements.'.$m, $components));

        return $q->selectRaw("($expression) AS engagements, (($expression) / NULLIF(measurements.impressions,0) * 100) AS engagement_rate");
    }

    public function metrics(User $actor, SocialMediaAccount $account, array $f): array
    {
        $filtered = SocialFilters::contentFiltered($f);
        $rows = $filtered ? DB::query()->fromSub($this->postStats($actor, $account, $f)->toBase(), 'facts') :
            SocialAccountMetric::where('social_account_id', $account->id)->where('starts_on', '>=', $f['from'])->where('ends_on', '<=', $f['to']);
        $sql = 'COUNT(*) AS measurement_count';
        foreach (self::COUNTS as $m) {
            $sql .= ", CASE WHEN COUNT($m)=COUNT(*) THEN SUM($m) ELSE NULL END AS $m";
        }
        $raw = (array) ($filtered ? $rows->selectRaw($sql)->first() : $rows->selectRaw($sql)->first()->getAttributes());
        $result = [];
        foreach (self::COUNTS as $m) {
            $result[$m] = isset($raw[$m]) ? (int) $raw[$m] : null;
        }
        $result['engagements'] = SocialMath::engagements($result, $this->settings($account)['engagement_components']);
        $result['engagement_rate'] = SocialMath::percent($result['engagements'], $result['impressions']);
        $publication = $this->posts($actor, $account, $f)->where('published_at', '>=', Date::parse($f['from'], $account->timezone)->utc())
            ->where('published_at', '<', Date::parse($f['to'], $account->timezone)->addDay()->utc());
        $result['posts'] = $publication->count();
        $result['followers'] = null;
        $result['starting_followers'] = null;
        $result['new_followers'] = null;
        if (! $filtered) {
            $end = SocialAccountMetric::where('social_account_id', $account->id)->where('ends_on', '<=', $f['to'])->whereNotNull('followers')->orderByDesc('ends_on')->value('followers');
            $begin = SocialAccountMetric::where('social_account_id', $account->id)->where('ends_on', Date::parse($f['from'])->subDay()->toDateString())->value('followers');
            $gains = SocialAccountMetric::where('social_account_id', $account->id)->where('starts_on', '>=', $f['from'])->where('ends_on', '<=', $f['to'])
                ->selectRaw('CASE WHEN COUNT(new_followers)=COUNT(*) THEN SUM(new_followers) ELSE NULL END AS gains')->first()->gains;
            $result['followers'] = $end === null ? null : (int) $end;
            $result['starting_followers'] = $begin === null ? null : (int) $begin;
            $result['new_followers'] = $gains === null ? null : (int) $gains;
        }
        $result['growth_rate'] = SocialMath::percent($result['new_followers'], $result['starting_followers']);
        $result['average_engagement'] = $result['posts'] && $result['engagements'] !== null ? round($result['engagements'] / $result['posts'], 2) : null;
        $result['average_impressions'] = $result['posts'] && $result['impressions'] !== null ? round($result['impressions'] / $result['posts'], 2) : null;
        $result['rows'] = (int) $raw['measurement_count'];

        return $result;
    }

    public function get(User $actor, SocialMediaAccount $account, array $f): array
    {
        app(SocialAccess::class)->authorize($actor, 'dashboard.view', $account);
        // Filters themselves are authorized, even when they would produce zero results.
        if (filled($f['event_id'] ?? null)) {
            abort_unless(app(EventAccess::class)->visibleTo(Event::query(), $actor)->whereKey($f['event_id'])->exists(), 404);
        }
        if (filled($f['campaign_id'] ?? null)) {
            abort_unless(SocialCampaign::where('social_account_id', $account->id)->whereKey($f['campaign_id'])->exists(), 404);
        }
        $settings = $this->settings($account);
        $settings['engagement_components'] = array_values(array_intersect(['reactions', 'comments', 'shares', 'clicks'], $settings['engagement_components']));
        abort_unless(count($settings['engagement_components']) > 0, 503);
        $metrics = $this->metrics($actor, $account, $f);
        $previous = $this->metrics($actor, $account, array_merge($f, ['from' => $f['comparison_from'], 'to' => $f['comparison_to']]));
        $changes = [];
        foreach ($metrics as $key => $value) {
            if ($key !== 'rows') {
                $changes[$key] = SocialMath::change($value, $previous[$key]);
            }
        }
        $stats = $this->postStats($actor, $account, $f);
        $contentQuery = DB::query()->fromSub((clone $stats)->toBase(), 'p');
        $contentSql = 'COUNT(*) measured_posts';
        foreach ([...self::COUNTS, 'engagements'] as $m) {
            $contentSql .= ", CASE WHEN COUNT($m)=COUNT(*) THEN SUM($m) ELSE NULL END AS $m";
        }
        $content = (array) $contentQuery->selectRaw($contentSql)->first();
        $content['posts'] = $metrics['posts'];
        $content['average_engagement'] = $content['measured_posts'] && $content['engagements'] !== null ? round($content['engagements'] / $content['measured_posts'], 2) : null;
        $content['average_impressions'] = $content['measured_posts'] && $content['impressions'] !== null ? round($content['impressions'] / $content['measured_posts'], 2) : null;
        $top = (clone $stats)->with(['contentType', 'campaign', 'event', 'tags'])->orderByDesc('engagements')->orderBy('social_posts.id')->limit($f['top'])->get();
        $average = DB::query()->fromSub((clone $stats)->toBase(), 'p')->avg('engagement_rate');
        $attention = (clone $stats)->with(['contentType', 'campaign', 'event', 'tags'])->whereRaw('measurements.impressions > 0')
            ->having('engagement_rate', '<', ($average ?? 0) * $settings['attention_ratio'])->orderBy('engagement_rate')->limit(20)->get();
        $groups = [];
        foreach (['content_type_id', 'campaign_id', 'event_id'] as $dimension) {
            $groups[$dimension] = DB::query()->fromSub((clone $stats)->toBase(), 'p')->whereNotNull($dimension)
                ->select($dimension)->selectRaw('COUNT(*) posts, CASE WHEN COUNT(impressions)=COUNT(*) THEN SUM(impressions) END impressions, CASE WHEN COUNT(reactions)=COUNT(*) THEN SUM(reactions) END reactions, CASE WHEN COUNT(comments)=COUNT(*) THEN SUM(comments) END comments, CASE WHEN COUNT(shares)=COUNT(*) THEN SUM(shares) END shares, CASE WHEN COUNT(clicks)=COUNT(*) THEN SUM(clicks) END clicks, CASE WHEN COUNT(engagements)=COUNT(*) THEN SUM(engagements) END engagements, AVG(engagement_rate) average_rate, CASE WHEN COUNT(engagements)=COUNT(*) AND COUNT(impressions)=COUNT(*) THEN SUM(engagements)/NULLIF(SUM(impressions),0)*100 END engagement_rate')
                ->selectRaw('AVG(impressions) average_impressions, AVG(clicks) average_clicks, AVG(reactions) average_reactions, AVG(comments) average_comments, AVG(shares) average_shares')
                ->groupBy($dimension)->orderByDesc('engagement_rate')->limit(100)->get();
        }
        $typeNames = SocialContentType::all()->mapWithKeys(fn ($t) => [$t->id => $t->label()]);
        $campaignNames = SocialCampaign::where('social_account_id', $account->id)->pluck('name', 'id');
        $eventNames = app(EventAccess::class)->visibleTo(Event::query(), $actor)->whereIn('id', $groups['event_id']->pluck('event_id'))->pluck('title', 'id');
        foreach (['content_type_id' => $typeNames, 'campaign_id' => $campaignNames, 'event_id' => $eventNames] as $dimension => $names) {
            $groups[$dimension]->each(function ($row) use ($dimension, $names) {
                $row->name = $names[$row->$dimension] ?? '—';
            });
        }
        $tags = DB::query()->fromSub((clone $stats)->toBase(), 'p')->join('social_post_tags', 'social_post_tags.social_post_id', '=', 'p.id')
            ->join('social_tags', 'social_tags.id', '=', 'social_post_tags.social_tag_id')->whereNull('social_tags.deleted_at')
            ->select('social_tags.id', 'social_tags.name')->selectRaw('COUNT(*) posts, AVG(engagement_rate) average_rate, CASE WHEN COUNT(impressions)=COUNT(*) THEN SUM(impressions) END impressions, CASE WHEN COUNT(engagements)=COUNT(*) THEN SUM(engagements) END engagements, AVG(clicks) average_clicks')
            ->groupBy('social_tags.id', 'social_tags.name')->orderByDesc('average_rate')->limit(100)->get();
        $targets = collect();
        $targetValues = [];
        if ($actor->can('linkedin.targets.view') && ! SocialFilters::contentFiltered($f)) {
            $targets = SocialTarget::where('social_account_id', $account->id)->where('active', true)->whereBetween('year', [substr($f['from'], 0, 4), substr($f['to'], 0, 4)])->get();
            foreach ($targets as $target) {
                $start = Date::create($target->year, $target->month ?: 1, 1);
                $end = $target->month ? $start->endOfMonth() : $start->endOfYear();
                $exact = $f['from'] === $start->toDateString() && $f['to'] === $end->toDateString();
                $target->actual = $exact ? ($metrics[$target->metric] ?? null) : null;
                $target->achievement = SocialMath::percent($target->actual, $target->value);
                $target->remaining = $target->actual === null ? null : max(0, $target->value - $target->actual);
                if ($exact) {
                    $targetValues[$target->metric] = $target->value;
                }
            }
        }
        $score = SocialMath::score($metrics, $targetValues, $settings['score_weights'], $settings['score_thresholds']);
        $audience = collect();
        if ($actor->can('linkedin.audience.view') && ! SocialFilters::contentFiltered($f)) {
            $date = SocialAudienceMetric::where('social_account_id', $account->id)->whereBetween('metric_date', [$f['from'], $f['to']])->max('metric_date');
            if ($date) {
                $audience = SocialAudienceMetric::where('social_account_id', $account->id)->where('metric_date', $date)->orderByDesc('percentage')->limit(200)->get()->groupBy('dimension');
            }
        }
        $trend = $this->trend($actor, $account, $f);
        $days = max(1, Date::parse($f['from'])->diffInDays(Date::parse($f['to'])) + 1);
        $coverage = SocialFilters::contentFiltered($f) ? null : (int) SocialAccountMetric::where('social_account_id', $account->id)->where('starts_on', '>=', $f['from'])->where('ends_on', '<=', $f['to'])->selectRaw('COALESCE(SUM(DATEDIFF(ends_on,starts_on)+1),0) covered')->first()->covered;
        $averagePostRate = $average;
        $publishing = ['weekly' => round($metrics['posts'] / $days * 7, 2), 'monthly' => round($metrics['posts'] / $days * 30.4375, 2)];
        $now = Date::now($account->timezone);
        foreach (['this_month' => [$now->startOfMonth(), $now->endOfMonth()], 'this_week' => [$now->startOfWeek(), $now->endOfWeek()]] as $key => [$s,$e]) {
            $publishing[$key] = $this->posts($actor, $account, $f)->whereBetween('published_at', [$s->utc(), $e->utc()])->count();
        }
        $summary = $this->summary($metrics, $changes, $groups, $top);
        $followersAsOf = SocialFilters::contentFiltered($f) ? null : SocialAccountMetric::where('social_account_id', $account->id)->where('ends_on', '<=', $f['to'])->whereNotNull('followers')->max('ends_on');
        $monthlyGrowth = $metrics['new_followers'] === null ? null : round($metrics['new_followers'] / $days * 30.4375, 2);
        $insights = collect();
        if ($actor->can('linkedin.ai.view')) {
            $fingerprint = app(SocialProvenance::class)->fingerprint($actor, $account);
            $insights = SocialAiInsight::where('social_account_id', $account->id)->where('user_id', $actor->id)->latest('id')->limit(20)->get()
                ->filter(fn ($row) => $row->filters === $f && hash_equals($row->snapshot['visibility'], $fingerprint));
        }
        $partialQuery = SocialFilters::contentFiltered($f) ? SocialPostMetric::whereIn('social_post_id', $this->posts($actor, $account, $f)->select('social_posts.id')) : SocialAccountMetric::where('social_account_id', $account->id);
        $partial = $partialQuery->where('starts_on', '<=', $f['to'])->where('ends_on', '>=', $f['from'])
            ->where(fn ($q) => $q->where('starts_on', '<', $f['from'])->orWhere('ends_on', '>', $f['to']))->exists();

        return compact('account', 'f', 'settings', 'metrics', 'previous', 'changes', 'top', 'attention', 'groups', 'tags', 'targets', 'score', 'audience', 'trend', 'publishing', 'summary', 'partial', 'insights', 'content', 'followersAsOf', 'monthlyGrowth', 'coverage', 'days', 'averagePostRate');
    }

    public function trend(User $actor, SocialMediaAccount $account, array $f): array
    {
        $filtered = SocialFilters::contentFiltered($f);
        $q = $filtered ? DB::table('social_post_metrics')->whereIn('social_post_id', $this->posts($actor, $account, $f)->select('social_posts.id')) :
            DB::table('social_account_metrics')->where('social_account_id', $account->id);
        $q->select('ends_on');
        foreach (self::COUNTS as $m) {
            $q->selectRaw("CASE WHEN COUNT($m)=COUNT(*) THEN SUM($m) ELSE NULL END AS $m");
        }
        if (! $filtered) {
            $q->selectRaw('MAX(followers) followers');
        }
        $rows = $q->where('starts_on', '>=', $f['from'])->where('ends_on', '<=', $f['to'])->groupBy('ends_on')->orderBy('ends_on')->get();
        $buckets = [];
        foreach ($rows as $r) {
            $date = Date::parse($r->ends_on);
            $key = match ($f['granularity']) {
                'week' => $date->startOfWeek()->toDateString(),'month' => $date->format('Y-m'),default => $date->toDateString()
            };
            $eng = SocialMath::engagements((array) $r, $this->settings($account)['engagement_components']);
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['date' => $key, 'impressions' => 0, 'engagements' => 0, 'clicks' => 0, 'followers' => null];
            }
            foreach (['impressions' => $r->impressions, 'clicks' => $r->clicks, 'engagements' => $eng] as $m => $v) {
                $buckets[$key][$m] = $buckets[$key][$m] === null || $v === null ? null : $buckets[$key][$m] + $v;
            }
            if (! $filtered) {
                $buckets[$key]['followers'] = $r->followers;
            }
        }

        return array_values($buckets);
    }

    private function summary(array $metrics, array $changes, array $groups, $top): string
    {
        $text = __('social.summary_template', array_map(fn ($v) => $v === null ? '—' : number_format($v, 2), array_intersect_key($metrics, array_flip(['impressions', 'new_followers', 'engagement_rate', 'clicks']))));
        foreach (['content_type_id' => 'best_type', 'campaign_id' => 'best_campaign', 'event_id' => 'best_event'] as $key => $label) {
            $text .= ' '.__('social.'.$label).': '.($groups[$key]->first()?->name ?? '—').'.';
        }
        $text .= ' '.__('social.best_post').': '.($top->first()?->title ?? '—').'.';
        foreach (['impressions', 'new_followers', 'engagements', 'clicks'] as $key) {
            $v = $changes[$key]['percent'];
            $text .= ' '.__('social.'.$key).': '.($v === null ? '—' : (($v > 0 ? '+' : '').number_format($v, 2).'%')).'.';
        }

        return $text;
    }
}
