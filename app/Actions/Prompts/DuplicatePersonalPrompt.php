<?php

namespace App\Actions\Prompts;

use App\Enums\PromptSource;
use App\Enums\PromptStatus;
use App\Enums\PromptVisibility;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DuplicatePersonalPrompt
{
    public function handle(User $actor, Prompt $prompt): Prompt
    {
        if ($prompt->source !== PromptSource::Personal) {
            throw new AuthorizationException;
        }
        Gate::forUser($actor)->authorize('duplicate', $prompt);

        return DB::transaction(function () use ($actor, $prompt) {
            $prompt->load('tags');
            $copy = $prompt->replicate(['published_at', 'published_by', 'revision_number']);
            $copy->forceFill(['owner_id' => $actor->id, 'source' => PromptSource::Personal, 'status' => PromptStatus::Draft,
                'visibility' => PromptVisibility::Private, 'title' => __('prompts.copy_title', ['title' => $prompt->title]),
                'slug' => Str::limit(Str::slug($prompt->slug.'-copy'), 165, '').'-'.Str::lower(Str::random(8)),
                'published_at' => null, 'published_by' => null, 'revision_number' => 1])->save();
            $copy->tags()->sync($prompt->tags->pluck('id'));

            return $copy;
        });
    }
}
