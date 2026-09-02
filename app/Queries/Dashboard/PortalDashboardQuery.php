<?php

namespace App\Queries\Dashboard;

use App\Enums\PromptSource;
use App\Models\Event;
use App\Models\Prompt;
use App\Models\User;
use App\Queries\Notifications\NotificationInbox;
use App\Services\Events\EventAccess;
use App\Services\Prompts\PromptAccess;
use Illuminate\Support\Collection;

class PortalDashboardQuery
{
    /**
     * @return array{sections: Collection<int, array{key: string}>, quickActions: Collection<int, array{key: string}>}
     */
    public function get(User $user): array
    {
        $visibleLibrary = app(PromptAccess::class)->visibleTo(Prompt::query(), $user);
        $recentPromptCount = Prompt::query()->where(function ($query) use ($user, $visibleLibrary) {
            $query->where(fn ($personal) => $personal->where('source', PromptSource::Personal)->where('owner_id', $user->id))
                ->orWhereIn('id', (clone $visibleLibrary)->select('prompts.id'));
        })->whereHas('uses', fn ($uses) => $uses->where('user_id', $user->id))->count();
        $visibleEvents = app(EventAccess::class)->visibleTo(Event::query(), $user);

        $sections = collect([
            ['key' => 'recent_ai_conversations', 'authorized' => $user->can('use-ai'), 'count' => $user->aiConversations()->whereNull('archived_at')->count(), 'route' => 'ai.index'],
            ['key' => 'favorite_prompts', 'authorized' => true, 'count' => (clone $visibleLibrary)->whereHas('favorites', fn ($favorites) => $favorites->where('user_id', $user->id))->count(), 'route' => 'my-prompts.index', 'params' => ['section' => 'favorites']],
            ['key' => 'recent_prompts', 'authorized' => true, 'count' => $recentPromptCount, 'route' => 'my-prompts.index', 'params' => ['section' => 'recent']],
            ['key' => 'personal_prompts', 'authorized' => true, 'count' => $user->prompts()->where('source', PromptSource::Personal)->count(), 'route' => 'my-prompts.index', 'params' => ['section' => 'personal']],
            ['key' => 'upcoming_events', 'authorized' => true, 'count' => (clone $visibleEvents)->where('ends_at', '>=', now())->count(), 'route' => 'events.index'],
            ['key' => 'recent_events', 'authorized' => true, 'count' => (clone $visibleEvents)->where('ends_at', '<', now())->count(), 'route' => 'events.index', 'params' => ['period' => 'past']],
            ['key' => 'notifications', 'authorized' => true, 'count' => app(NotificationInbox::class)->unread($user), 'route' => 'notifications.index'],
        ])->where('authorized', true)->map(fn (array $section) => collect($section)->except('authorized')->all())->values();

        $quickActions = collect([
            ['key' => 'ai_assistant', 'authorized' => $user->can('use-ai'), 'route' => 'ai.create'],
            ['key' => 'prompt_library', 'authorized' => true, 'route' => 'prompts.index'],
            ['key' => 'events', 'authorized' => true, 'route' => 'events.index'],
        ])->where('authorized', true)->map(fn (array $action) => ['key' => $action['key'], 'route' => $action['route'] ?? null])->values();

        return compact('sections', 'quickActions');
    }
}
