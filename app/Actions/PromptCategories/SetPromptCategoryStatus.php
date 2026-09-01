<?php

namespace App\Actions\PromptCategories;

use App\Actions\Identity\RecordAccountAudit;
use App\Models\PromptCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SetPromptCategoryStatus
{
    public function handle(User $actor, PromptCategory $category, bool $active): PromptCategory
    {
        Gate::forUser($actor)->authorize('update', $category);

        return DB::transaction(function () use ($actor, $category, $active) {
            $category = PromptCategory::lockForUpdate()->findOrFail($category->id);
            $category->update(['is_active' => $active]);
            app(RecordAccountAudit::class)->handle($actor, 'prompt_category.status_changed', ['category_id' => $category->id, 'is_active' => $active], $actor);

            return $category;
        });
    }
}
