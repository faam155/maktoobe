<?php

namespace App\Actions\Ai;

use App\Enums\AiRequestStatus;
use App\Jobs\ProcessAiRequest;
use App\Models\AiConversation;
use App\Models\AiRequest;
use App\Models\Prompt;
use App\Models\User;
use App\Services\Ai\AiModelAccess;
use App\Services\Brand\BrandGuidelineContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SendMessage
{
    public function handle(User $actor, AiConversation $conversation, array $input): AiRequest
    {
        Gate::forUser($actor)->authorize('update', $conversation);
        $data = Validator::make($input, ['content' => ['required', 'string', 'min:1', 'max:20000'],
            'model' => ['nullable', 'string', 'max:100'], 'prompt_id' => ['nullable', 'integer', 'exists:prompts,id'],
            'use_brand_guidelines' => ['nullable', 'boolean'], 'client_operation_id' => ['required', 'uuid']])->validate();
        $existing = AiRequest::where('user_id', $actor->id)->where('client_operation_id', $data['client_operation_id'])->first();
        if ($existing) {
            return $existing;
        }
        $model = app(AiModelAccess::class)->authorize($actor, $data['model'] ?? $conversation->model);
        $prompt = filled($data['prompt_id'] ?? null) ? Prompt::findOrFail($data['prompt_id']) : null;
        if ($prompt) {
            Gate::forUser($actor)->authorize('view', $prompt);
        }
        $content = trim((string) $data['content']);
        if ($prompt) {
            $content = $prompt->content."\n\n".__('ai.additional_context')."\n".$content;
        }
        $brandVersion = null;
        if ((bool) ($data['use_brand_guidelines'] ?? false)) {
            $brandVersion = app(BrandGuidelineContext::class)->activeVersion();
            if (! app(BrandGuidelineContext::class)->forVersion($brandVersion)) {
                throw ValidationException::withMessages(['use_brand_guidelines' => __('brand.no_active_context')]);
            }
        }

        return DB::transaction(function () use ($actor, $conversation, $data, $model, $prompt, $brandVersion, $content) {
            $conversation = AiConversation::lockForUpdate()->findOrFail($conversation->id);
            Gate::forUser($actor)->authorize('update', $conversation);
            $message = $conversation->messages()->create(['role' => 'user', 'model' => $model, 'content' => $content]);
            if ($conversation->messages()->count() === 1) {
                $conversation->update(['title' => Str::limit(preg_replace('/\s+/u', ' ', trim($data['content'])), 80), 'model' => $model]);
            }
            $conversation->update(['last_message_at' => $message->created_at, 'archived_at' => null]);
            $request = AiRequest::create(['user_id' => $actor->id, 'conversation_id' => $conversation->id,
                'prompt_id' => $prompt?->id, 'brand_guideline_version_id' => $brandVersion?->id, 'prompt_revision' => $prompt?->revision_number, 'prompt_snapshot' => $prompt?->content,
                'user_message_id' => $message->id, 'client_operation_id' => $data['client_operation_id'], 'model' => $model,
                'status' => AiRequestStatus::Queued, 'settings_snapshot' => ['max_output_tokens' => config('ai.max_output_tokens'), 'temperature' => config('ai.temperature'), 'use_brand_guidelines' => (bool) $brandVersion], 'requested_at' => now()]);
            ProcessAiRequest::dispatch($request->id)->afterCommit();

            return $request;
        });
    }
}
