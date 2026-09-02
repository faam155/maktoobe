<?php

namespace App\Queries\Events;

use App\Models\Event;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class AdminEventIndexQuery
{
    public function handle(User $actor, array $filters): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('create', Event::class);

        return Event::query()->with(['category.translations', 'organizer:id,name'])
            ->when(filled($filters['search'] ?? null), fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%'.trim($filters['search']).'%')->orWhere('location', 'like', '%'.trim($filters['search']).'%')))
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('starts_at')->paginate(15)->withQueryString();
    }
}
