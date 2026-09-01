<?php

namespace App\Actions\Ai;

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Ai\AiModelAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CreateConversation
{
    public function handle(User $actor, array $input): AiConversation
    {
        Gate::forUser($actor)->authorize('create', AiConversation::class);
        $data = Validator::make($input, ['model' => ['nullable', 'string', 'max:100']])->validate();
        $model = app(AiModelAccess::class)->authorize($actor, $data['model'] ?? null);

        return AiConversation::create(['user_id' => $actor->id, 'title' => __('ai.new_conversation'), 'model' => $model]);
    }
}
