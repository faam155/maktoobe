<?php

namespace App\Actions\Prompts;

use App\Models\Prompt;
use App\Models\PromptUse;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class RecordPromptCopy
{
    public function handle(User $actor, Prompt $prompt, array $input): PromptUse
    {
        Gate::forUser($actor)->authorize('view', $prompt);
        $data = Validator::make($input, ['client_operation_id' => ['required', 'uuid']])->validate();

        return PromptUse::firstOrCreate(
            ['user_id' => $actor->id, 'client_operation_id' => $data['client_operation_id']],
            ['prompt_id' => $prompt->id, 'kind' => 'copy'],
        );
    }
}
