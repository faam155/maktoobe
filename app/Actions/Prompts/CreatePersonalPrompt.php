<?php

namespace App\Actions\Prompts;

use App\Enums\PromptSource;
use App\Enums\PromptStatus;
use App\Enums\PromptVisibility;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreatePersonalPrompt
{
    use ValidatesPrompt;

    public function handle(User $actor, array $input): Prompt
    {
        $input = array_merge($input, ['visibility' => PromptVisibility::Private->value, 'user_ids' => [], 'role_ids' => []]);
        $data = $this->normalize(Validator::make($this->prepare($input), $this->rules($actor))->validate());

        return DB::transaction(function () use ($actor, $data) {
            $prompt = Prompt::create(collect($data)->except(['tags', 'user_ids', 'role_ids'])->merge([
                'owner_id' => $actor->id, 'source' => PromptSource::Personal, 'status' => PromptStatus::Draft,
            ])->all());
            app(SyncPromptRelations::class)->handle($prompt, $actor, $data);

            return $prompt;
        });
    }
}
