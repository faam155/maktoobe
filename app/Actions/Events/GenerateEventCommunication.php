<?php

namespace App\Actions\Events;

use App\Jobs\GenerateEventCommunicationContent;
use App\Models\Event;
use App\Models\EventCommunication;
use App\Models\EventCommunicationGeneration;
use App\Models\User;
use App\Services\Ai\AiModelAccess;
use App\Services\Brand\BrandGuidelineContext;
use App\Services\Events\CommunicationInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GenerateEventCommunication
{
    public function handle(User $actor, Event $event, array $input): EventCommunicationGeneration
    {
        Gate::forUser($actor)->authorize('generate', [EventCommunication::class, $event]);
        $slot = app(CommunicationInput::class)->slot($input);
        $data = Validator::make($input, ['operation' => ['required', Rule::in(['generate', 'improve', 'translate', 'regenerate'])], 'instructions' => ['nullable', 'string', 'max:4000'], 'model' => ['required', 'string', 'max:100'], 'use_brand_guidelines' => ['nullable', 'boolean'], 'client_operation_id' => ['required', 'uuid'], 'revision_number' => ['required', 'integer', 'min:0']])->validate();

        return DB::transaction(function () use ($actor, $event, $slot, $data) {
            $event = Event::lockForUpdate()->findOrFail($event->id);
            $actor = User::lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($actor)->authorize('generate', [EventCommunication::class, $event]);
            $existing = EventCommunicationGeneration::where('user_id', $actor->id)->where('client_operation_id', $data['client_operation_id'])->first();
            if ($existing) {
                abort_unless($existing->event_id === $event->id && $existing->communication->type === $slot['type'] && $existing->communication->language === $slot['language'], 409);

                return $existing;
            }
            $model = app(AiModelAccess::class)->authorize($actor, $data['model']);
            $key = 'event-communication-ai:'.$actor->id;
            if (RateLimiter::tooManyAttempts($key, 10) || EventCommunicationGeneration::where('user_id', $actor->id)->whereIn('status', ['queued', 'processing'])->count() >= 5) {
                throw ValidationException::withMessages(['operation' => __('communications.limit')]);
            }
            $communication = $event->communications()->where($slot)->first();
            app(CommunicationInput::class)->revision($communication, (int) $data['revision_number']);
            if ($communication?->archived_at) {
                throw ValidationException::withMessages(['operation' => __('communications.archived')]);
            }
            $source = $data['operation'] === 'translate'
                ? $event->communications()->where('type', $slot['type'])->where('language', $slot['language'] === 'ar' ? 'en' : 'ar')->whereNull('archived_at')->first()
                : $communication;
            if (in_array($data['operation'], ['improve', 'translate'], true) && blank($source?->content)) {
                throw ValidationException::withMessages(['operation' => __('communications.source_required')]);
            }
            $brand = null;
            if ($data['use_brand_guidelines'] ?? false) {
                $brand = app(BrandGuidelineContext::class)->activeVersion();
                if (! app(BrandGuidelineContext::class)->forVersion($brand)) {
                    throw ValidationException::withMessages(['use_brand_guidelines' => __('brand.no_active_context')]);
                }
            }
            $communication ??= $event->communications()->create($slot + ['created_by' => $actor->id, 'updated_by' => $actor->id]);
            $snapshot = ['event' => ['title' => $event->title, 'description' => mb_substr($event->description ?? '', 0, 20000), 'starts_at' => $event->starts_at->toIso8601String(), 'ends_at' => $event->ends_at->toIso8601String(), 'timezone' => $event->timezone, 'location' => $event->location], 'type' => $slot['type'], 'language' => $slot['language'], 'operation' => $data['operation'], 'instructions' => $data['instructions'] ?? '', 'source' => ['title' => $source?->title, 'content' => $source?->content, 'communication_id' => $source?->id, 'revision_number' => $source?->revision_number]];
            $generation = $communication->generations()->create(['event_id' => $event->id, 'user_id' => $actor->id, 'client_operation_id' => $data['client_operation_id'], 'base_revision' => $communication->revision_number, 'operation' => $data['operation'], 'model' => $model, 'input_snapshot' => $snapshot, 'settings_snapshot' => ['max_output_tokens' => max(64, min(4000, (int) config('ai.max_output_tokens'))), 'temperature' => config('ai.temperature')], 'brand_guideline_version_id' => $brand?->id]);
            RateLimiter::hit($key, 60);
            GenerateEventCommunicationContent::dispatch($generation->id)->afterCommit();

            return $generation;
        });
    }
}
