<?php

namespace App\Actions\Prompts;

use App\Actions\Identity\RecordAccountAudit;
use App\Enums\PromptSource;
use App\Enums\PromptStatus;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CreatePrompt
{
    use ValidatesPrompt;

    public function handle(User $actor, array $input): Prompt
    {
        Gate::forUser($actor)->authorize('create', Prompt::class);
        $data = $this->normalize(Validator::make($this->prepare($input), $this->rules($actor))->validate());

        return DB::transaction(function () use ($actor, $data) {
            $prompt = Prompt::create(collect($data)->except(['tags', 'user_ids', 'role_ids'])->merge([
                'owner_id' => $actor->id, 'source' => PromptSource::Library, 'status' => PromptStatus::Draft,
            ])->all());
            app(SyncPromptRelations::class)->handle($prompt, $actor, $data);
            app(RecordAccountAudit::class)->handle($actor, 'prompt.created', ['prompt_id' => $prompt->id, 'slug' => $prompt->slug], $actor);

            return $prompt;
        });
    }
}
