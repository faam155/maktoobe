<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Prompts\CreatePersonalPrompt;
use App\Actions\Prompts\DeletePrompt;
use App\Actions\Prompts\DuplicatePersonalPrompt;
use App\Actions\Prompts\RecordPromptCopy;
use App\Actions\Prompts\UpdatePersonalPrompt;
use App\Enums\PromptSource;
use App\Models\Prompt;
use App\Models\PromptCategory;
use App\Models\Tag;
use App\Queries\Prompts\MyPromptsQuery;
use App\Services\Prompts\PromptAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MyPromptController
{
    public function index(Request $request, MyPromptsQuery $query): View
    {
        $filters = $request->validate(['section' => ['nullable', 'in:personal,favorites,recent'], 'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'], 'tag' => ['nullable', 'string', 'max:80']]);

        $actor = $request->user();
        $section = $filters['section'] ?? 'personal';
        $visibleLibrary = app(PromptAccess::class)->visibleTo(Prompt::query(), $actor);
        $filterPrompts = match ($section) {
            'favorites' => $visibleLibrary->whereHas('favorites', fn ($favorites) => $favorites->where('user_id', $actor->id)),
            'recent' => Prompt::query()->where(function ($builder) use ($actor, $visibleLibrary) {
                $builder->where(fn ($personal) => $personal->where('source', PromptSource::Personal)->where('owner_id', $actor->id))
                    ->orWhereIn('id', $visibleLibrary->select('prompts.id'));
            })->whereHas('uses', fn ($uses) => $uses->where('user_id', $actor->id)),
            default => Prompt::query()->where('source', PromptSource::Personal)->where('owner_id', $actor->id),
        };

        return view('portal.my-prompts.index', ['prompts' => $query->handle($actor, $filters), 'filters' => $filters,
            'categories' => PromptCategory::where('is_active', true)->with('translations')->orderBy('display_order')->get(),
            'tags' => Tag::whereHas('prompts', fn ($prompts) => $prompts->whereIn('prompts.id', $filterPrompts->select('prompts.id')))->orderBy('display_name')->get()]);
    }

    public function create(): View
    {
        return view('portal.my-prompts.create', ['categories' => $this->categories()]);
    }

    public function store(Request $request, CreatePersonalPrompt $action): RedirectResponse
    {
        $prompt = $action->handle($request->user(), $request->all());

        return redirect()->route('my-prompts.show', $prompt)->with('status', __('prompts.personal_created'));
    }

    public function show(Prompt $prompt): View
    {
        Gate::authorize('view', $prompt);
        abort_unless($prompt->source === PromptSource::Personal, 404);

        return view('portal.my-prompts.show', ['prompt' => $prompt->load(['category.translations', 'tags'])->loadCount('uses')]);
    }

    public function edit(Prompt $prompt): View
    {
        Gate::authorize('update', $prompt);
        abort_unless($prompt->source === PromptSource::Personal, 404);

        return view('portal.my-prompts.edit', ['prompt' => $prompt->load('tags'), 'categories' => $this->categories()]);
    }

    public function update(Request $request, Prompt $prompt, UpdatePersonalPrompt $action): RedirectResponse
    {
        abort_unless($prompt->source === PromptSource::Personal, 404);
        $prompt = $action->handle($request->user(), $prompt, $request->all());

        return redirect()->route('my-prompts.show', $prompt)->with('status', __('prompts.personal_updated'));
    }

    public function destroy(Request $request, Prompt $prompt, DeletePrompt $action): RedirectResponse
    {
        abort_unless($prompt->source === PromptSource::Personal, 404);
        $action->handle($request->user(), $prompt);

        return redirect()->route('my-prompts.index')->with('status', __('prompts.personal_deleted'));
    }

    public function duplicate(Request $request, Prompt $prompt, DuplicatePersonalPrompt $action): RedirectResponse
    {
        abort_unless($prompt->source === PromptSource::Personal, 404);
        $copy = $action->handle($request->user(), $prompt);

        return redirect()->route('my-prompts.edit', $copy)->with('status', __('prompts.personal_duplicated'));
    }

    public function copy(Request $request, Prompt $prompt, RecordPromptCopy $action): JsonResponse
    {
        $action->handle($request->user(), $prompt, $request->all());

        return response()->json(['recorded' => true]);
    }

    private function categories()
    {
        return PromptCategory::where('is_active', true)->with('translations')->orderBy('display_order')->get();
    }
}
