<?php

namespace App\Actions\PromptCategories;

use App\Models\PromptCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ValidatesPromptCategory
{
    private function prepare(array $input): array
    {
        if (blank($input['slug'] ?? null) && filled($input['name_en'] ?? null)) {
            $input['slug'] = Str::slug((string) $input['name_en']);
        }

        return $input;
    }

    private function rules(?PromptCategory $category = null): array
    {
        return [
            'name_en' => ['required', 'string', 'max:100'],
            'name_ar' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('prompt_categories')->ignore($category?->id)],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function normalize(array $data): array
    {
        foreach (['name_en', 'name_ar', 'description_en', 'description_ar', 'icon'] as $key) {
            $data[$key] = filled($data[$key] ?? null) ? trim((string) $data[$key]) : null;
        }
        $data['slug'] = Str::slug((string) $data['slug']);
        $data['is_active'] = (bool) $data['is_active'];

        return $data;
    }
}
