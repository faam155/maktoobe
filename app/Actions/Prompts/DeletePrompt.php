<?php

namespace App\Actions\Prompts;

use App\Actions\Identity\RecordAccountAudit;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeletePrompt
{
    public function handle(User $actor, Prompt $prompt): void
    {
        Gate::forUser($actor)->authorize('delete', $prompt);
        DB::transaction(function () use ($actor, $prompt) {
            $prompt = Prompt::lockForUpdate()->findOrFail($prompt->id);
            $prompt->delete();
            app(RecordAccountAudit::class)->handle($actor, 'prompt.deleted', ['prompt_id' => $prompt->id, 'slug' => $prompt->slug], $actor);
        });
    }
}
