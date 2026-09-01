<?php

namespace App\Actions\Prompts;

use App\Actions\Identity\RecordAccountAudit;
use App\Enums\PromptStatus;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdatePrompt
{
    use ValidatesPrompt;

    public function handle(User $actor, Prompt $prompt, array $input): Prompt
    {
        Gate::forUser($actor)->authorize('update', $prompt);
        $data = $this->normalize(Validator::make($this->prepare($input), $this->rules($actor, $prompt))->validate());

        return DB::transaction(function () use ($actor, $prompt, $data) {
            $prompt = Prompt::lockForUpdate()->findOrFail($prompt->id);
            $before = ['slug' => $prompt->slug, 'revision' => $prompt->revision_number, 'visibility' => $prompt->visibility->value];
            $updates = collect($data)->except(['tags', 'user_ids', 'role_ids'])->all();
            $updates['revision_number'] = $prompt->revision_number + 1;
            if ($prompt->status === PromptStatus::Published) {
                $updates['status'] = PromptStatus::Draft;
                $updates['published_at'] = null;
                $updates['published_by'] = null;
            }
            $prompt->update($updates);
            app(SyncPromptRelations::class)->handle($prompt, $actor, $data);
            app(RecordAccountAudit::class)->handle($actor, 'prompt.updated', ['prompt_id' => $prompt->id, 'before' => $before, 'after' => ['slug' => $prompt->slug, 'revision' => $prompt->revision_number, 'visibility' => $prompt->visibility->value]], $actor);

            return $prompt;
        });
    }
}
