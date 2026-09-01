<?php

namespace App\Queries\PromptCategories;

use App\Models\PromptCategory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class PromptCategoryIndexQuery
{
    public function handle(User $actor, array $filters): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', PromptCategory::class);
        $search = trim((string) ($filters['search'] ?? ''));
        $status = in_array($filters['status'] ?? null, ['active', 'inactive'], true) ? $filters['status'] : null;

        return PromptCategory::query()
            ->with(['creator:id,name', 'translations'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('slug', 'like', "%{$search}%")
                    ->orWhereHas('translations', fn ($translation) => $translation->where('name', 'like', "%{$search}%"));
            }))
            ->when($status, fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('display_order')->orderBy('id')
            ->paginate(15)->withQueryString();
    }
}
