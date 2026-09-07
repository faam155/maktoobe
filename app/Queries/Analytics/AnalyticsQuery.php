<?php

namespace App\Queries\Analytics;

use App\Models\AiConversation;
use App\Models\AiRequest;
use App\Models\Event;
use App\Models\EventCommunication;
use App\Models\EventCommunicationGeneration;
use App\Models\EventFile;
use App\Models\EventReport;
use App\Models\Prompt;
use App\Models\PromptCategory;
use App\Models\PromptFavorite;
use App\Models\PromptUse;
use App\Models\User;
use App\Services\Events\EventAccess;
use App\Services\Prompts\PromptAccess;
use App\Support\Reporting\ReportPeriod;
use Illuminate\Support\Facades\Gate;

class AnalyticsQuery
{
    public function get(User $actor, ReportPeriod $period): array
    {
        Gate::forUser($actor)->authorize('access-admin');
        Gate::forUser($actor)->authorize('view-analytics');
        abort_unless($actor->status->value === 'active' && $actor->hasVerifiedEmail() && ! $actor->trashed(), 403);
        $metrics = [];
        if ($actor->can('manage-users')) {
            $users = $period->apply(User::query())->selectRaw("COUNT(*) total, SUM(status='active') active, SUM(status='disabled') disabled")->first();
            $metrics += ['total_users' => (int) $users->total, 'active_users' => (int) $users->active, 'disabled_users' => (int) $users->disabled];
        }
        $library = app(PromptAccess::class)->visibleTo(Prompt::query(), $actor);
        $events = app(EventAccess::class)->visibleTo(Event::query(), $actor);
        $eventIds = (clone $events)->select('events.id');
        $metrics['administrator_prompts'] = $period->apply(clone $library)->count();
        $metrics['personal_prompts'] = $period->apply(Prompt::where('source', 'personal')->where('owner_id', $actor->id))->count();
        $metrics['favorite_prompts'] = $period->apply(PromptFavorite::where('user_id', $actor->id)->whereIn('prompt_id', (clone $library)->select('prompts.id')))->count();

        if ($actor->can('use-ai')) {
            $requests = $period->apply(AiRequest::where('user_id', $actor->id)->whereHas('conversation', fn ($q) => $q->where('user_id', $actor->id)), 'requested_at')
                ->selectRaw("COUNT(*) requests, SUM(CASE WHEN status='completed' THEN total_tokens ELSE 0 END) tokens, SUM(status='completed' AND total_tokens IS NULL) unknown_tokens")->first();
            $generations = $period->apply(EventCommunicationGeneration::where('user_id', $actor->id)->whereIn('event_id', clone $eventIds))
                ->selectRaw("COUNT(*) requests, SUM(CASE WHEN status='completed' THEN total_tokens ELSE 0 END) tokens, SUM(status='completed' AND total_tokens IS NULL) unknown_tokens")->first();
            $metrics['ai_requests'] = (int) $requests->requests + (int) $generations->requests;
            $metrics['ai_conversations'] = $period->apply(AiConversation::where('user_id', $actor->id))->count();
            $metrics['ai_tokens'] = (int) $requests->tokens + (int) $generations->tokens;
            $metrics['unknown_tokens'] = (int) $requests->unknown_tokens + (int) $generations->unknown_tokens;
        }
        $eventCounts = $period->apply(clone $events, 'starts_at')->selectRaw("COUNT(*) total, SUM(status='completed') completed, SUM(status='cancelled') cancelled, SUM(status IN ('planned','confirmed') AND starts_at >= ?) upcoming", [now()])->first();
        $metrics += ['total_events' => (int) $eventCounts->total, 'upcoming_events' => (int) $eventCounts->upcoming, 'completed_events' => (int) $eventCounts->completed, 'cancelled_events' => (int) $eventCounts->cancelled];
        $metrics['event_photos'] = $period->apply(EventFile::whereIn('event_id', clone $eventIds)->where('category', 'photos')->where('scan_status', 'clean'))->count();
        $metrics['event_reports'] = $period->apply(EventReport::whereIn('event_id', clone $eventIds)->whereHas('currentVersion.file', fn ($q) => $q->where('scan_status', 'clean')))->count();
        $metrics['event_communications'] = $period->apply(EventCommunication::whereIn('event_id', clone $eventIds)->whereNull('archived_at')->where('revision_number', '>', 0))->count();

        $uses = $period->apply(PromptUse::query(), 'prompt_uses.created_at')->whereIn('prompt_id', (clone $library)->select('prompts.id'));
        $topPrompts = (clone $uses)->selectRaw('prompt_id, COUNT(*) uses')->groupBy('prompt_id')->orderByDesc('uses')->orderBy('prompt_id')->limit(10)->get();
        $names = (clone $library)->whereIn('id', $topPrompts->pluck('prompt_id'))->pluck('title', 'id');
        $topPrompts = $topPrompts->filter(fn ($row) => $names->has($row->prompt_id))->map(fn ($row) => ['name' => $names[$row->prompt_id], 'count' => (int) $row->uses])->all();
        $categories = (clone $uses)->join('prompts', 'prompts.id', '=', 'prompt_uses.prompt_id')->whereNotNull('prompts.category_id')->selectRaw('prompts.category_id, COUNT(*) uses')->groupBy('prompts.category_id')->orderByDesc('uses')->orderBy('prompts.category_id')->limit(10)->get();
        $categoryNames = PromptCategory::with('translations')->whereIn('id', $categories->pluck('category_id'))->get()->keyBy('id');
        $categories = $categories->filter(fn ($row) => $categoryNames->has($row->category_id))->map(fn ($row) => ['name' => $categoryNames[$row->category_id]->localizedName(), 'count' => (int) $row->uses])->all();

        return compact('metrics', 'topPrompts', 'categories');
    }
}
