<?php

namespace App\Actions\PromptCategories;

use App\Actions\Identity\RecordAccountAudit;
use App\Models\PromptCategory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DeletePromptCategory
{
    public function handle(User $actor, PromptCategory $category): void
    {
        Gate::forUser($actor)->authorize('delete', $category);

        if (Schema::hasTable('prompts') && DB::table('prompts')->where('category_id', $category->id)->exists()) {
            throw ValidationException::withMessages(['category' => __('categories.delete_in_use')]);
        }

        try {
            DB::transaction(function () use ($actor, $category) {
                $category = PromptCategory::lockForUpdate()->findOrFail($category->id);
                $audit = ['category_id' => $category->id, 'slug' => $category->slug];
                $category->delete();
                app(RecordAccountAudit::class)->handle($actor, 'prompt_category.deleted', $audit, $actor);
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw ValidationException::withMessages(['category' => __('categories.delete_in_use')]);
            }
            throw $exception;
        }
    }
}
