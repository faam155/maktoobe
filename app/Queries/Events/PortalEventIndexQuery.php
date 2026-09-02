<?php

namespace App\Queries\Events;

use App\Models\Event;
use App\Models\User;
use App\Services\Events\EventAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class PortalEventIndexQuery
{
    public function handle(User $actor, array $filters): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Event::class);
        $query = app(EventAccess::class)->visibleTo(Event::query(), $actor)->with(['category.translations', 'organizer:id,name']);
        $query->when(filled($filters['search'] ?? null), fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%'.trim($filters['search']).'%')->orWhere('location', 'like', '%'.trim($filters['search']).'%')))
            ->when(($filters['period'] ?? 'upcoming') === 'upcoming', fn ($q) => $q->where('ends_at', '>=', now())->orderBy('starts_at'))
            ->when(($filters['period'] ?? null) === 'past', fn ($q) => $q->where('ends_at', '<', now())->orderByDesc('starts_at'));

        if (($filters['period'] ?? null) === 'all') {
            $query->orderBy('starts_at');
        }

        return $query->orderBy('id')->paginate(12)->withQueryString();
    }
}
