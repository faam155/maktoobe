<?php

namespace App\Queries\Dashboard;

use App\Models\User;
use Illuminate\Support\Collection;

class PortalDashboardQuery
{
    /**
     * @return array{sections: Collection<int, array{key: string}>, quickActions: Collection<int, array{key: string}>}
     */
    public function get(User $user): array
    {
        $sections = collect([
            ['key' => 'recent_ai_conversations', 'authorized' => $user->can('use-ai')],
            ['key' => 'favorite_prompts', 'authorized' => true],
            ['key' => 'recent_prompts', 'authorized' => true],
            ['key' => 'personal_prompts', 'authorized' => true],
            ['key' => 'upcoming_events', 'authorized' => true],
            ['key' => 'recent_events', 'authorized' => true],
            ['key' => 'notifications', 'authorized' => true],
        ])->where('authorized', true)->map(fn (array $section) => ['key' => $section['key']])->values();

        $quickActions = collect([
            ['key' => 'ai_assistant', 'authorized' => $user->can('use-ai')],
            ['key' => 'prompt_library', 'authorized' => true, 'route' => 'prompts.index'],
            ['key' => 'event_calendar', 'authorized' => true],
        ])->where('authorized', true)->map(fn (array $action) => ['key' => $action['key'], 'route' => $action['route'] ?? null])->values();

        return compact('sections', 'quickActions');
    }
}
