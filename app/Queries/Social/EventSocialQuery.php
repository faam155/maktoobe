<?php

namespace App\Queries\Social;

use App\Models\Event;
use App\Models\SocialMediaAccount;
use App\Models\User;
use App\Services\Social\SocialFilters;
use Illuminate\Support\Facades\Gate;

class EventSocialQuery
{
    public function get(User $actor, Event $event): array
    {
        Gate::forUser($actor)->authorize('view', $event);
        if (! $actor->can('linkedin.dashboard.view')) {
            return [];
        }
        $result = [];
        foreach (SocialMediaAccount::where('active', true)->whereHas('platform', fn ($q) => $q->where('slug', 'linkedin')->where('active', true))->whereHas('posts', fn ($q) => $q->where('event_id', $event->id))->get() as $account) {
            $from = $event->starts_at->copy()->setTimezone($account->timezone)->startOfMonth()->toDateString();
            $to = $event->starts_at->copy()->setTimezone($account->timezone)->endOfMonth()->toDateString();
            $f = SocialFilters::make($account, ['period' => 'custom', 'from' => $from, 'to' => $to, 'event_id' => $event->id]);
            $result[] = ['account' => $account, 'f' => $f, 'metrics' => app(SocialAnalyticsQuery::class)->metrics($actor, $account, $f)];
        }

        return $result;
    }
}
