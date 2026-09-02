<?php

namespace App\Queries\Dashboard;

use App\Enums\AccountStatus;
use App\Enums\EventStatus;
use App\Models\AiConversation;
use App\Models\Event;
use App\Models\Prompt;
use App\Models\User;
use App\Services\Events\EventAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminDashboardQuery
{
    /**
     * @return array{
     *     userMetrics: Collection<int, array{key: string, value: int, status: ?string}>,
     *     unavailableMetrics: Collection<int, array{key: string}>,
     *     recentActivity: ?Collection<int, array{action: string, subject: string, actor: ?string, created_at: Carbon}>
     * }
     */
    public function get(User $actor): array
    {
        Gate::forUser($actor)->authorize('access-admin');

        $userMetrics = collect();
        if ($actor->can('manage-users')) {
            $counts = User::query()->selectRaw(
                'COUNT(*) AS total, SUM(status = ?) AS active, SUM(status = ?) AS disabled',
                [AccountStatus::Active->value, AccountStatus::Disabled->value]
            )->first();
            $userMetrics = collect([
                ['key' => 'total_users', 'value' => (int) $counts->total, 'status' => null],
                ['key' => 'active_users', 'value' => (int) $counts->active, 'status' => AccountStatus::Active->value],
                ['key' => 'disabled_users', 'value' => (int) $counts->disabled, 'status' => AccountStatus::Disabled->value],
            ]);
        }

        $promptMetrics = $actor->can('manage-prompts')
            ? collect([['key' => 'prompt_count', 'value' => Prompt::where('source', 'library')->count()]]) : collect();

        $aiMetrics = $actor->canAny(['manage-ai-settings', 'view-analytics'])
            ? collect([['key' => 'ai_conversations', 'value' => AiConversation::count()]]) : collect();

        $visibleEvents = app(EventAccess::class)->visibleTo(Event::query(), $actor);
        $eventMetrics = $actor->canAny(['manage-events', 'view-analytics']) ? collect([
            ['key' => 'upcoming_events', 'value' => (clone $visibleEvents)->where('ends_at', '>=', now())->where('status', '!=', EventStatus::Cancelled)->count()],
            ['key' => 'completed_events', 'value' => (clone $visibleEvents)->where('status', EventStatus::Completed)->count()],
        ]) : collect();
        $unavailableMetrics = collect();

        return [
            'userMetrics' => $userMetrics,
            'promptMetrics' => $promptMetrics,
            'aiMetrics' => $aiMetrics,
            'eventMetrics' => $eventMetrics,
            'unavailableMetrics' => $unavailableMetrics,
            'recentActivity' => $actor->can('manage-users') ? $this->recentActivity($actor) : null,
        ];
    }

    /** @return Collection<int, array{action: string, subject: string, actor: ?string, created_at: Carbon}> */
    private function recentActivity(User $actor): Collection
    {
        return DB::table('account_audits as audit')
            ->join('users as subject', 'subject.id', '=', 'audit.user_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'audit.actor_id')
            ->select(['audit.action', 'audit.created_at', 'subject.name as subject_name', 'actor.name as actor_name'])
            ->latest('audit.created_at')
            ->limit(6)
            ->get()
            ->map(fn (object $activity) => [
                'action' => $activity->action,
                'subject' => $activity->subject_name,
                'actor' => $activity->actor_name,
                'created_at' => Carbon::parse($activity->created_at, 'UTC')->setTimezone($actor->timezone),
            ]);
    }
}
