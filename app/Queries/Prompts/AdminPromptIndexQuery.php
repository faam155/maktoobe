<?php

namespace App\Queries\Prompts;

use App\Enums\PromptSource;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class AdminPromptIndexQuery
{
    public function handle(User $actor, array $filters): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('create', Prompt::class);
        $search = trim((string) ($filters['search'] ?? ''));

        return Prompt::query()->where('source', PromptSource::Library->value)
            ->with(['category.translations', 'tags', 'owner:id,name'])->withCount('uses')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")
                    ->orWhereFullText('content', $search, ['mode' => 'boolean'])
                    ->orWhereHas('tags', fn ($tags) => $tags->where('display_name', 'like', "%{$search}%"));
            }))
            ->when(in_array($filters['status'] ?? null, ['draft', 'published', 'archived'], true), fn ($query) => $query->where('status', $filters['status']))
            ->when(filled($filters['category'] ?? null), fn ($query) => $query->where('category_id', $filters['category']))
            ->latest('updated_at')->paginate(15)->withQueryString();
    }
}
