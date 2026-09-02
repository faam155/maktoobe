<?php

namespace App\Queries\Events;

use App\Models\Event;
use App\Models\EventCommunication;
use App\Models\User;
use App\Services\Ai\AiModelAccess;
use App\Services\Events\CommunicationInput;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class EventCommunicationQuery
{
    public function handle(User $actor, Event $event, array $input): array
    {
        Gate::forUser($actor)->authorize('viewAny', [EventCommunication::class, $event]);
        $slot = app(CommunicationInput::class)->slot($input + ['type' => 'internal_email', 'language' => app()->getLocale()]);
        Validator::make($input, ['page' => ['nullable', 'integer', 'min:1', 'max:100000']])->validate();
        $communication = $event->communications()->where($slot)->first();
        $canManage = Gate::forUser($actor)->allows('manage', [EventCommunication::class, $event]);
        $canGenerate = Gate::forUser($actor)->allows('generate', [EventCommunication::class, $event]);
        // Viewers receive current non-archived content only; editors may inspect history.
        $history = $canManage ? $communication?->revisions()->with('creator')->latest('version_number')->paginate(10)->withQueryString() : null;
        $generations = $canGenerate ? ($communication?->generations()->where('user_id', $actor->id)->latest('id')->limit(5)->get() ?? collect()) : collect();
        if (! $canManage && $communication?->archived_at) {
            $communication = null;
        }
        $models = $canGenerate ? app(AiModelAccess::class)->modelsFor($actor) : [];

        return compact('event', 'slot', 'communication', 'canManage', 'canGenerate', 'history', 'generations', 'models');
    }
}
