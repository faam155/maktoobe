<?php

namespace App\Actions\PromptCategories;

use App\Actions\Identity\RecordAccountAudit;
use App\Models\PromptCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdatePromptCategory
{
    use ValidatesPromptCategory;

    public function handle(User $actor, PromptCategory $category, array $input): PromptCategory
    {
        Gate::forUser($actor)->authorize('update', $category);
        $data = $this->normalize(Validator::make($this->prepare($input), $this->rules($category))->validate());

        return DB::transaction(function () use ($actor, $category, $data) {
            $category = PromptCategory::lockForUpdate()->findOrFail($category->id);
            $category->load('translations');
            $before = ['name_en' => $category->name_en, 'name_ar' => $category->name_ar, 'slug' => $category->slug, 'is_active' => $category->is_active];
            $category->update(collect($data)->except(['name_en', 'name_ar', 'description_en', 'description_ar'])->all());
            DB::table('prompt_category_translations')->upsert([
                ['category_id' => $category->id, 'locale' => 'en', 'name' => $data['name_en'], 'description' => $data['description_en']],
                ['category_id' => $category->id, 'locale' => 'ar', 'name' => $data['name_ar'], 'description' => $data['description_ar']],
            ], ['category_id', 'locale'], ['name', 'description']);
            $category->load('translations');
            app(RecordAccountAudit::class)->handle($actor, 'prompt_category.updated', ['category_id' => $category->id, 'before' => $before, 'after' => ['name_en' => $category->name_en, 'name_ar' => $category->name_ar, 'slug' => $category->slug, 'is_active' => $category->is_active]], $actor);

            return $category;
        });
    }
}
