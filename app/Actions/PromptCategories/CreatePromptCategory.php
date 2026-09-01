<?php

namespace App\Actions\PromptCategories;

use App\Actions\Identity\RecordAccountAudit;
use App\Models\PromptCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CreatePromptCategory
{
    use ValidatesPromptCategory;

    public function handle(User $actor, array $input): PromptCategory
    {
        Gate::forUser($actor)->authorize('create', PromptCategory::class);
        $data = $this->normalize(Validator::make($this->prepare($input), $this->rules())->validate());

        return DB::transaction(function () use ($actor, $data) {
            $data['display_order'] = ((int) PromptCategory::lockForUpdate()->max('display_order')) + 1;
            $data['created_by'] = $actor->id;
            $category = PromptCategory::create(collect($data)->except(['name_en', 'name_ar', 'description_en', 'description_ar'])->all());
            DB::table('prompt_category_translations')->insert([
                ['category_id' => $category->id, 'locale' => 'en', 'name' => $data['name_en'], 'description' => $data['description_en']],
                ['category_id' => $category->id, 'locale' => 'ar', 'name' => $data['name_ar'], 'description' => $data['description_ar']],
            ]);
            app(RecordAccountAudit::class)->handle($actor, 'prompt_category.created', ['category_id' => $category->id, 'slug' => $category->slug], $actor);

            return $category;
        });
    }
}
