<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Ai\CreateConversation;
use App\Actions\Ai\SendMessage;
use App\Enums\AiRequestStatus;
use App\Jobs\ProcessAiRequest;
use App\Models\AiConversation;
use App\Models\AiRequest;
use App\Models\Prompt;
use App\Queries\Ai\ConversationHistoryQuery;
use App\Services\Ai\AiModelAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AiAssistantController
{
    public function index(Request $request, ConversationHistoryQuery $history): View
    {
        return view('portal.ai.index', ['conversations' => $history->get($request->user(), $request->only(['search', 'status', 'sort']))]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', AiConversation::class);
        $prompt = filled($request->query('prompt')) ? Prompt::where('slug', $request->query('prompt'))->firstOrFail() : null;
        if ($prompt) {
            Gate::authorize('view', $prompt);
        }

        return view('portal.ai.create', ['prompt' => $prompt, 'models' => app(AiModelAccess::class)->modelsFor($request->user())]);
    }

    public function store(Request $request, CreateConversation $create, SendMessage $send): RedirectResponse
    {
        $conversation = $create->handle($request->user(), $request->all());
        try {
            $send->handle($request->user(), $conversation, $request->all());
        } catch (\Throwable $exception) {
            $conversation->forceDelete();
            throw $exception;
        }

        return redirect()->route('ai.show', $conversation);
    }

    public function show(Request $request, AiConversation $conversation): View
    {
        Gate::authorize('view', $conversation);

        $messages = $conversation->messages()->latest('id')->paginate(30, ['*'], 'messages')->withQueryString();
        $messages->setCollection($messages->getCollection()->reverse()->values());
        $messageIds = $messages->getCollection()->pluck('id');
        $conversation->setRelation('requests', $conversation->requests()->whereIn('user_message_id', $messageIds)->latest('id')->get());

        return view('portal.ai.show', compact('conversation', 'messages') + [
            'models' => app(AiModelAccess::class)->modelsFor($request->user()),
            'recentConversations' => $request->user()->aiConversations()->whereNull('archived_at')
                ->orderByRaw('COALESCE(last_message_at, created_at) DESC')->limit(12)->get(),
        ]);
    }

    public function send(Request $request, AiConversation $conversation, SendMessage $action): RedirectResponse
    {
        $action->handle($request->user(), $conversation, $request->all());

        return back()->with('status', __('ai.queued'));
    }

    public function status(AiConversation $conversation, AiRequest $aiRequest): JsonResponse
    {
        Gate::authorize('view', $conversation);
        abort_unless($aiRequest->conversation_id === $conversation->id, 404);

        return response()->json(['status' => $aiRequest->status->value, 'failure_code' => $aiRequest->failure_code]);
    }

    public function rename(Request $request, AiConversation $conversation): RedirectResponse
    {
        Gate::authorize('update', $conversation);
        $data = $request->validate(['title' => ['required', 'string', 'max:160']]);
        $conversation->update(['title' => trim($data['title'])]);

        return back()->with('status', __('ai.renamed'));
    }

    public function archive(Request $request, AiConversation $conversation): RedirectResponse
    {
        Gate::authorize('update', $conversation);
        $data = $request->validate(['archived' => ['required', 'boolean']]);
        $conversation->update(['archived_at' => $data['archived'] ? now() : null]);

        return redirect()->route('ai.index', ['status' => $data['archived'] ? 'archived' : 'active'])
            ->with('status', $data['archived'] ? __('ai.archived') : __('ai.restored'));
    }

    public function destroy(AiConversation $conversation): RedirectResponse
    {
        Gate::authorize('delete', $conversation);
        DB::transaction(function () use ($conversation) {
            $conversation->requests()->whereIn('status', [AiRequestStatus::Queued, AiRequestStatus::Processing])
                ->update(['status' => AiRequestStatus::Cancelled, 'failure_code' => 'conversation_deleted', 'cancelled_at' => now(), 'finished_at' => now()]);
            $conversation->delete();
        });

        return redirect()->route('ai.index')->with('status', __('ai.deleted'));
    }

    public function cancel(AiConversation $conversation, AiRequest $aiRequest): RedirectResponse
    {
        Gate::authorize('update', $conversation);
        abort_unless($aiRequest->conversation_id === $conversation->id, 404);
        $aiRequest->newQuery()->whereKey($aiRequest->id)->whereIn('status', [AiRequestStatus::Queued, AiRequestStatus::Processing])
            ->update(['status' => AiRequestStatus::Cancelled, 'failure_code' => 'cancelled_by_user', 'cancelled_at' => now(), 'finished_at' => now()]);

        return back()->with('status', __('ai.cancelled'));
    }

    public function retry(Request $request, AiConversation $conversation, AiRequest $aiRequest): RedirectResponse
    {
        Gate::authorize('update', $conversation);
        abort_unless($aiRequest->conversation_id === $conversation->id && $aiRequest->status === AiRequestStatus::Failed, 404);
        $model = app(AiModelAccess::class)->authorize($request->user(), $aiRequest->model);
        $copy = AiRequest::create(['user_id' => $request->user()->id, 'conversation_id' => $conversation->id,
            'prompt_id' => $aiRequest->prompt_id, 'prompt_revision' => $aiRequest->prompt_revision, 'prompt_snapshot' => $aiRequest->prompt_snapshot,
            'user_message_id' => $aiRequest->user_message_id, 'client_operation_id' => (string) Str::uuid(), 'model' => $model,
            'status' => AiRequestStatus::Queued, 'settings_snapshot' => $aiRequest->settings_snapshot, 'requested_at' => now()]);
        ProcessAiRequest::dispatch($copy->id)->afterCommit();

        return back()->with('status', __('ai.queued'));
    }
}
