<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Enums\AccountStatus;
use App\Enums\AiRequestStatus;
use App\Exceptions\AiProviderException;
use App\Models\AiRequest;
use App\Models\PromptUse;
use App\Services\Brand\BrandGuidelineContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessAiRequest implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public int $requestId)
    {
        $this->onQueue('ai');
        $this->timeout = max(10, (int) config('ai.timeout') + 5);
    }

    public function handle(AiProvider $provider): void
    {
        $request = DB::transaction(function () {
            $request = AiRequest::with(['user.roles', 'conversation', 'brandGuidelineVersion.guideline'])->lockForUpdate()->find($this->requestId);
            if (! $request || $request->status !== AiRequestStatus::Queued) {
                return null;
            }
            if (! $request->user || $request->user->status !== AccountStatus::Active || ! $request->user->can('use-ai') || $request->conversation->user_id !== $request->user_id) {
                $request->update(['status' => AiRequestStatus::Cancelled, 'failure_code' => 'access_revoked', 'cancelled_at' => now(), 'finished_at' => now()]);

                return null;
            }
            $request->update(['status' => AiRequestStatus::Processing, 'started_at' => now()]);

            return $request;
        });
        if (! $request) {
            return;
        }

        $history = $request->conversation->messages()->where('id', '<=', $request->user_message_id)->latest('id')->limit(30)->get();
        $characters = 0;
        $messages = $history->takeWhile(function ($message) use (&$characters) {
            $characters += mb_strlen($message->content);

            return $characters <= 100000;
        })->reverse()->map(fn ($message) => ['role' => $message->role, 'content' => $message->content])->values()->all();
        $brandContext = app(BrandGuidelineContext::class)->forVersion($request->brandGuidelineVersion);
        if ($brandContext) {
            array_unshift($messages, ['role' => 'developer', 'content' => $brandContext]);
        }
        try {
            $result = $provider->generate($messages, $request->model, $request->settings_snapshot, hash('sha256', 'maktoobe:'.$request->user_id));
            DB::transaction(function () use ($request, $result) {
                $locked = AiRequest::lockForUpdate()->findOrFail($request->id);
                if ($locked->status === AiRequestStatus::Cancelled) {
                    return;
                }
                $assistant = $locked->conversation->messages()->create(['role' => 'assistant', 'model' => $locked->model, 'content' => $result->content,
                    'input_tokens' => $result->inputTokens, 'output_tokens' => $result->outputTokens, 'total_tokens' => $result->totalTokens]);
                $locked->update(['assistant_message_id' => $assistant->id, 'status' => AiRequestStatus::Completed,
                    'provider_request_id' => $result->providerRequestId, 'input_tokens' => $result->inputTokens,
                    'output_tokens' => $result->outputTokens, 'total_tokens' => $result->totalTokens, 'finished_at' => now()]);
                if ($locked->prompt_id) {
                    PromptUse::create(['user_id' => $locked->user_id, 'prompt_id' => $locked->prompt_id,
                        'ai_request_id' => $locked->id, 'kind' => 'ai', 'client_operation_id' => $locked->client_operation_id]);
                }
                $locked->conversation->update(['last_message_at' => $assistant->created_at]);
            });
        } catch (AiProviderException $exception) {
            AiRequest::whereKey($request->id)->where('status', AiRequestStatus::Processing)->update(['status' => AiRequestStatus::Failed, 'failure_code' => $exception->safeCode, 'finished_at' => now()]);
        } catch (\Throwable $exception) {
            report($exception);
            AiRequest::whereKey($request->id)->where('status', AiRequestStatus::Processing)->update(['status' => AiRequestStatus::Failed, 'failure_code' => 'internal_error', 'finished_at' => now()]);
        }
    }
}
