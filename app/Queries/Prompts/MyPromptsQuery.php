<?php

namespace App\Queries\Prompts;

use App\Enums\PromptSource;
use App\Models\Prompt;
use App\Models\User;
use App\Services\Prompts\PromptAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MyPromptsQuery
{
    public function handle(User $actor, array $filters): LengthAwarePaginator
    {
        $section = $filters['section'] ?? 'personal';
        $query = match ($section) {
            'favorites' => app(PromptAccess::class)->visibleTo(Prompt::query(), $actor)
                ->whereHas('favorites', fn ($favorites) => $favorites->where('user_id', $actor->id)),
            'recent' => Prompt::query()->where(function ($query) use ($actor) {
                $query->where(fn ($personal) => $personal->where('source', PromptSource::Personal->value)->where('owner_id', $actor->id))
                    ->orWhereIn('id', app(PromptAccess::class)->visibleTo(Prompt::query(), $actor)->select('prompts.id'));
            })->whereHas('uses', fn ($uses) => $uses->where('user_id', $actor->id))
                ->orderByDesc(
                    fn ($subquery) => $subquery->select('created_at')->from('prompt_uses')
                        ->whereColumn('prompt_id', 'prompts.id')->where('user_id', $actor->id)->latest()->limit(1)
                ),
            default => Prompt::query()->where('source', PromptSource::Personal->value)->where('owner_id', $actor->id)->latest('updated_at'),
        };

        $search = trim((string) ($filters['search'] ?? ''));
        $query->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
            $query->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")
                ->orWhereFullText('content', $search, ['mode' => 'boolean'])
                ->orWhereHas('tags', fn ($tags) => $tags->where('display_name', 'like', "%{$search}%"));
        }))->when(filled($filters['category'] ?? null), fn ($query) => $query->where('category_id', $filters['category']))
            ->when(filled($filters['tag'] ?? null), fn ($query) => $query->whereHas('tags', fn ($tags) => $tags->where('canonical_name', $filters['tag'])));

        return $query->with(['category.translations', 'tags'])->withCount('uses')
            ->withExists(['favorites as is_favorite' => fn ($favorites) => $favorites->where('user_id', $actor->id)])
            ->paginate(12)->withQueryString();
    }
}
