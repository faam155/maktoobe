<?php

namespace App\Actions\Prompts;

use App\Actions\Identity\RecordAccountAudit;
use App\Enums\PromptStatus;
use App\Enums\PromptVisibility;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DuplicatePrompt
{
    public function handle(User $actor, Prompt $prompt): Prompt
    {
        Gate::forUser($actor)->authorize('duplicate', $prompt);

        return DB::transaction(function () use ($actor, $prompt) {
            $prompt->load('tags');
            $copy = $prompt->replicate(['published_at', 'published_by', 'revision_number']);
            $copy->owner_id = $actor->id;
            $copy->title = __('prompts.copy_title', ['title' => $prompt->title]);
            $base = Str::limit(Str::slug($prompt->slug.'-copy'), 165, '');
            $copy->slug = $base.'-'.Str::lower(Str::random(8));
            $copy->status = PromptStatus::Draft;
            $copy->visibility = PromptVisibility::Private;
            $copy->published_at = null;
            $copy->published_by = null;
            $copy->revision_number = 1;
            $copy->save();
            $copy->tags()->sync($prompt->tags->pluck('id'));
            app(RecordAccountAudit::class)->handle($actor, 'prompt.duplicated', ['prompt_id' => $copy->id, 'source_prompt_id' => $prompt->id], $actor);

            return $copy;
        });
    }
}
