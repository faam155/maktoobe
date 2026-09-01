<?php

namespace App\Actions\Prompts;

use App\Enums\PromptSource;
use App\Enums\PromptVisibility;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdatePersonalPrompt
{
    use ValidatesPrompt;

    public function handle(User $actor, Prompt $prompt, array $input): Prompt
    {
        if ($prompt->source !== PromptSource::Personal) {
            throw new AuthorizationException;
        }
        Gate::forUser($actor)->authorize('update', $prompt);
        $input = array_merge($input, ['visibility' => PromptVisibility::Private->value, 'user_ids' => [], 'role_ids' => []]);
        $data = $this->normalize(Validator::make($this->prepare($input), $this->rules($actor, $prompt))->validate());

        return DB::transaction(function () use ($actor, $prompt, $data) {
            $locked = Prompt::lockForUpdate()->findOrFail($prompt->id);
            Gate::forUser($actor)->authorize('update', $locked);
            $locked->update(collect($data)->except(['tags', 'user_ids', 'role_ids'])->merge([
                'visibility' => PromptVisibility::Private, 'revision_number' => $locked->revision_number + 1,
            ])->all());
            app(SyncPromptRelations::class)->handle($locked, $actor, $data);

            return $locked;
        });
    }
}
