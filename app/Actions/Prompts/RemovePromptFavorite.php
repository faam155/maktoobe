<?php

namespace App\Actions\Prompts;

use App\Models\Prompt;
use App\Models\PromptFavorite;
use App\Models\User;

class RemovePromptFavorite
{
    public function handle(User $actor, Prompt $prompt): void
    {
        PromptFavorite::where('user_id', $actor->id)->where('prompt_id', $prompt->id)->delete();
    }
}
