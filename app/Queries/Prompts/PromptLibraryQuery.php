<?php

namespace App\Queries\Prompts;

use App\Models\Prompt;
use App\Models\User;
use App\Services\Prompts\PromptAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class PromptLibraryQuery
{
    public function handle(User $actor, array $filters): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Prompt::class);
        $query = app(PromptAccess::class)->visibleTo(Prompt::query(), $actor)
            ->with(['category.translations', 'tags'])->withCount('uses')
            ->withExists(['favorites as is_favorite' => fn ($favorites) => $favorites->where('user_id', $actor->id)]);
        $search = trim((string) ($filters['search'] ?? ''));
        $query->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
            $query->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")
                ->orWhereFullText('content', $search, ['mode' => 'boolean'])
                ->orWhereHas('tags', fn ($tags) => $tags->where('display_name', 'like', "%{$search}%"));
        }))->when(filled($filters['category'] ?? null), fn ($query) => $query->where('category_id', $filters['category']))
            ->when(filled($filters['tag'] ?? null), fn ($query) => $query->whereHas('tags', fn ($tags) => $tags->where('canonical_name', $filters['tag'])));

        match ($filters['sort'] ?? 'newest') {
            'title' => $query->orderBy('title'),
            'popular' => $query->orderByDesc('uses_count')->latest('published_at'),
            default => $query->latest('published_at'),
        };

        return $query->paginate(12)->withQueryString();
    }
}
