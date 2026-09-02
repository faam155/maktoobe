<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\EventCommunication;
use App\Models\EventCommunicationGeneration;
use App\Models\User;
use App\Services\Events\CommunicationInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveEventCommunication
{
    public function handle(User $actor, Event $event, array $input): EventCommunication
    {
        Gate::forUser($actor)->authorize('manage', [EventCommunication::class, $event]);
        $slot = app(CommunicationInput::class)->slot($input);
        $data = Validator::make($input, ['title' => ['nullable', 'string', 'max:180'], 'content' => ['nullable', 'string', 'max:20000', 'required_unless:status,draft'], 'status' => ['required', Rule::in(EventCommunication::STATUSES)], 'revision_number' => ['required', 'integer', 'min:0']])->validate();

        return DB::transaction(function () use ($actor, $event, $slot, $data) {
            $event = Event::lockForUpdate()->findOrFail($event->id);
            Gate::forUser($actor)->authorize('manage', [EventCommunication::class, $event]);
            $communication = $event->communications()->where($slot)->first();
            app(CommunicationInput::class)->revision($communication, (int) $data['revision_number']);
            $communication ??= $event->communications()->create($slot + ['created_by' => $actor->id]);
            $this->persist($actor, $communication, $data['title'] ?? '', $data['content'] ?? '', $data['status'], 'manual');

            return $communication;
        });
    }

    public function apply(User $actor, Event $event, EventCommunicationGeneration $generation): void
    {
        Gate::forUser($actor)->authorize('generate', [EventCommunication::class, $event]);
        abort_unless($generation->event_id === $event->id && $generation->user_id === $actor->id, 404);
        DB::transaction(function () use ($actor, $event, $generation) {
            $event = Event::lockForUpdate()->findOrFail($event->id);
            Gate::forUser($actor)->authorize('generate', [EventCommunication::class, $event]);
            $generation = EventCommunicationGeneration::lockForUpdate()->findOrFail($generation->id);
            $communication = $event->communications()->findOrFail($generation->event_communication_id);
            abort_unless($generation->status === 'completed' && ! $generation->applied_at && ! $communication->archived_at, 409);
            app(CommunicationInput::class)->revision($communication, $generation->base_revision);
            $this->persist($actor, $communication, $generation->result['title'], $generation->result['content'], 'draft', 'ai', $generation->id);
            $generation->update(['applied_at' => now()]);
        });
    }

    public function archive(User $actor, Event $event, EventCommunication $communication, array $input): void
    {
        Gate::forUser($actor)->authorize('manage', [EventCommunication::class, $event]);
        abort_unless($communication->event_id === $event->id, 404);
        $data = Validator::make($input, ['confirm' => ['accepted'], 'revision_number' => ['required', 'integer', 'min:0']])->validate();
        DB::transaction(function () use ($actor, $event, $communication, $data) {
            $event = Event::lockForUpdate()->findOrFail($event->id);
            Gate::forUser($actor)->authorize('manage', [EventCommunication::class, $event]);
            $communication = $event->communications()->findOrFail($communication->id);
            app(CommunicationInput::class)->revision($communication, (int) $data['revision_number']);
            $this->persist($actor, $communication, $communication->title, $communication->content ?? '', 'draft', 'archive');
            $communication->update(['archived_at' => now()]);
            $communication->generations()->whereIn('status', ['queued', 'processing'])->update(['status' => 'cancelled', 'finished_at' => now()]);
        });
    }

    private function persist(User $actor, EventCommunication $communication, string $title, string $content, string $status, string $origin, ?int $generationId = null): void
    {
        $communication->update(['title' => $title, 'content' => $content, 'status' => $status, 'updated_by' => $actor->id, 'archived_at' => null, 'revision_number' => $communication->revision_number + 1]);
        $revision = $communication->revisions()->create(['version_number' => $communication->revision_number, 'title' => $title, 'content' => $content, 'status' => $status, 'origin' => $origin, 'generation_id' => $generationId, 'created_by' => $actor->id]);
        $communication->event->activities()->create(['actor_id' => $actor->id, 'action' => 'event.communication_'.$origin, 'metadata' => ['communication_id' => $communication->id, 'revision_id' => $revision->id], 'created_at' => now()]);
    }
}
