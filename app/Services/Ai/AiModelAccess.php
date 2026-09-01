<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class AiModelAccess
{
    public function modelsFor(User $user): array
    {
        $all = array_values(array_unique(config('ai.models', [])));
        $roleMap = config('ai.role_models', []);
        $matched = $user->roles->contains(fn ($role) => array_key_exists($role->name, $roleMap));
        $restricted = $user->roles->flatMap(fn ($role) => $roleMap[$role->name] ?? [])->unique()->values()->all();

        return $matched ? array_values(array_intersect($all, $restricted)) : $all;
    }

    public function authorize(User $user, ?string $model): string
    {
        $model = $model ?: config('ai.default_model');
        if (! in_array($model, $this->modelsFor($user), true)) {
            throw ValidationException::withMessages(['model' => __('ai.invalid_model')]);
        }

        return $model;
    }
}
