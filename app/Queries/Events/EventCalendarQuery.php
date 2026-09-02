<?php

namespace App\Queries\Events;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\User;
use App\Services\Events\EventAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventCalendarQuery
{
    public function handle(User $actor, array $input, bool $administration = false): array
    {
        Gate::forUser($actor)->authorize($administration ? 'create' : 'viewAny', Event::class);
        $filters = Validator::make($input, [
            'view' => ['nullable', Rule::in(['month', 'week', 'list'])],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-02-01', 'before:2100-12-01'],
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d', 'after_or_equal:1900-02-01', 'before:2100-12-01'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from', 'before:2100-12-01'],
            'status' => ['nullable', Rule::enum(EventStatus::class)],
            'category' => ['nullable', 'integer', 'min:1'], 'organizer' => ['nullable', 'integer', 'min:1'],
            'visibility' => [$actor->can('manage-events') ? 'nullable' : 'prohibited', Rule::enum(EventVisibility::class)],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ])->validate();
        $timezone = $actor->timezone ?: 'UTC';
        $view = $filters['view'] ?? 'month';
        $anchor = isset($filters['date']) ? CarbonImmutable::parse($filters['date'], $timezone)->startOfDay() : CarbonImmutable::now($timezone)->startOfDay();
        $start = $view === 'week' ? $anchor->startOfWeek(1) : $anchor->startOfMonth();
        $end = $view === 'week' ? $start->addWeek() : $start->addMonth();
        if ($view === 'month') {
            $start = $start->startOfWeek(1);
            $end = $end->subDay()->endOfWeek(0)->startOfDay()->addDay();
        }
        if (filled($filters['from'] ?? null)) {
            $from = CarbonImmutable::parse($filters['from'], $timezone)->startOfDay();
            $to = CarbonImmutable::parse($filters['to'], $timezone)->startOfDay()->addDay();
            if ($from->diffInDays($to) > 62) {
                throw ValidationException::withMessages(['to' => __('calendar.range_limit')]);
            }
            // A custom range uses the agenda rather than an irregular month/week grid.
            $start = $from;
            $end = $to;
            $anchor = $from;
            $view = 'list';
        }
        $base = app(EventAccess::class)->visibleTo(Event::query(), $actor)
            ->where('starts_at', '<', $end->utc())->where('ends_at', '>', $start->utc());
        $categories = EventCategory::with('translations')->whereIn('id', (clone $base)->select('category_id'))->orderBy('display_order')->get();
        $organizers = User::withTrashed()->whereIn('id', (clone $base)->select('organizer_id'))->orderBy('name')->get(['id', 'name']);
        $query = (clone $base)
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->when(filled($filters['category'] ?? null), fn ($q) => $q->where('category_id', $filters['category']))
            ->when(filled($filters['organizer'] ?? null), fn ($q) => $q->where('organizer_id', $filters['organizer']))
            ->when(filled($filters['visibility'] ?? null), fn ($q) => $q->where('visibility', $filters['visibility']));
        $events = $query->select(['id', 'slug', 'title', 'starts_at', 'ends_at', 'timezone', 'status', 'category_id', 'organizer_id', 'location'])
            ->with(['category.translations', 'organizer:id,name'])->orderBy('starts_at')->orderBy('id')->paginate(100)->withQueryString();
        $days = collect();
        for ($day = $start; $day->lt($end); $day = $day->addDay()) {
            $days->push(['date' => $day, 'events' => $events->getCollection()->filter(fn (Event $event) => $event->starts_at->lt($day->addDay()->utc()) && $event->ends_at->gt($day->utc()))]);
        }

        return compact('events', 'days', 'start', 'end', 'anchor', 'view', 'timezone', 'filters', 'categories', 'organizers', 'administration');
    }
}
