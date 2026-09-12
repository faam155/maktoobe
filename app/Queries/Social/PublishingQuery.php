<?php

namespace App\Queries\Social;

use App\Models\SocialMediaAccount;
use App\Models\User;
use Carbon\CarbonImmutable as Date;

class PublishingQuery
{
    public function get(User $actor, SocialMediaAccount $account, array $f): array
    {
        $buckets = [];
        $days = [];
        $hours = [];
        $start = Date::parse($f['from'], $account->timezone);
        $end = Date::parse($f['to'], $account->timezone);
        for ($date = $start; $date <= $end; $date = $date->addDay()) {
            $key = $this->bucket($date, $f['granularity']);
            $buckets[$key] = ['date' => $key, 'posts' => 0];
        }
        $query = app(SocialAnalyticsQuery::class)->posts($actor, $account, $f)
            ->where('published_at', '>=', $start->utc())->where('published_at', '<', $end->addDay()->utc())->select('published_at')->orderBy('id');
        foreach ($query->cursor() as $post) {
            $date = Date::instance($post->published_at)->setTimezone($account->timezone);
            $buckets[$this->bucket($date, $f['granularity'])]['posts']++;
            $days[$date->dayOfWeekIso] = ($days[$date->dayOfWeekIso] ?? 0) + 1;
            $hours[$date->hour] = ($hours[$date->hour] ?? 0) + 1;
        }

        return ['trend' => array_values($buckets), 'days' => $days, 'hours' => $hours];
    }

    private function bucket(Date $date, string $granularity): string
    {
        return match ($granularity) {
            'month' => $date->format('Y-m'),'week' => $date->startOfWeek()->toDateString(),default => $date->toDateString()
        };
    }
}
