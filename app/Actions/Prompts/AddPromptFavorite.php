<?php

namespace App\Actions\Prompts;

use App\Models\Prompt;
use App\Models\PromptFavorite;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AddPromptFavorite
{
    public function handle(User $actor, Prompt $prompt): PromptFavorite
    {
        Gate::forUser($actor)->authorize('favorite', $prompt);

        return PromptFavorite::firstOrCreate(['user_id' => $actor->id, 'prompt_id' => $prompt->id]);
    }
}
