<?php

namespace App\Actions\Prompts;

use App\Enums\PromptVisibility;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait ValidatesPrompt
{
    private function prepare(array $input): array
    {
        if (blank($input['slug'] ?? null) && filled($input['title'] ?? null)) {
            $input['slug'] = Str::slug((string) $input['title']);
        }
        $input['tags'] = array_values(array_filter(array_map('trim', is_array($input['tags'] ?? null) ? $input['tags'] : explode(',', (string) ($input['tags'] ?? '')))));

        return $input;
    }

    private function rules(User $actor, ?Prompt $prompt = null): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('prompts')->ignore($prompt?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'content' => ['required', 'string', 'min:10', 'max:100000'],
            'content_locale' => ['nullable', 'string', 'max:10', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/'],
            'category_id' => ['nullable', 'integer', Rule::exists('prompt_categories', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('is_active', true))],
            'visibility' => ['required', Rule::enum(PromptVisibility::class)],
            'user_ids' => ['sometimes', 'array', 'max:100'],
            'user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('status', 'active'))],
            'role_ids' => ['sometimes', 'array', 'max:50'],
            'role_ids.*' => ['integer', 'distinct', Rule::exists('roles', 'id')->where('guard_name', 'web')],
            'tags' => ['sometimes', 'array', 'max:10'],
            'tags.*' => ['string', 'min:1', 'max:40', 'regex:/^[\pL\pN][\pL\pN _-]*$/u'],
        ];
    }

    private function normalize(array $data): array
    {
        foreach (['title', 'slug', 'description', 'content', 'content_locale'] as $key) {
            $data[$key] = filled($data[$key] ?? null) ? trim((string) $data[$key]) : null;
        }
        $data['slug'] = Str::slug((string) $data['slug']);
        $data['visibility'] = PromptVisibility::from($data['visibility']);
        $data['user_ids'] = array_values(array_unique($data['user_ids'] ?? []));
        $data['role_ids'] = array_values(array_unique($data['role_ids'] ?? []));
        $data['tags'] = array_values(array_unique(array_map(fn ($tag) => trim(preg_replace('/\s+/u', ' ', $tag)), $data['tags'] ?? [])));

        if ($data['visibility'] === PromptVisibility::SelectedUsers && $data['user_ids'] === []) {
            throw ValidationException::withMessages(['user_ids' => __('prompts.audience_required')]);
        }
        if ($data['visibility'] === PromptVisibility::SelectedRoles && $data['role_ids'] === []) {
            throw ValidationException::withMessages(['role_ids' => __('prompts.audience_required')]);
        }

        return $data;
    }
}
