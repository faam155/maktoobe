<?php

namespace App\Actions\Prompts;

use App\Enums\PromptVisibility;
use App\Models\Prompt;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Str;

class SyncPromptRelations
{
    public function handle(Prompt $prompt, User $actor, array $data): void
    {
        $tags = collect($data['tags'])->map(function (string $display) {
            $canonical = Str::lower(trim(preg_replace('/\s+/u', ' ', $display)));

            return Tag::firstOrCreate(['canonical_name' => $canonical], ['display_name' => $display]);
        });
        $prompt->tags()->sync($tags->pluck('id'));
        $prompt->allowedUsers()->sync($data['visibility'] === PromptVisibility::SelectedUsers
            ? collect($data['user_ids'])->mapWithKeys(fn ($id) => [$id => ['granted_by' => $actor->id, 'created_at' => now()]])->all() : []);
        $prompt->allowedRoles()->sync($data['visibility'] === PromptVisibility::SelectedRoles
            ? collect($data['role_ids'])->mapWithKeys(fn ($id) => [$id => ['granted_by' => $actor->id, 'created_at' => now()]])->all() : []);
    }
}
