<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Prompts\RecordPromptCopy;
use App\Models\Prompt;
use App\Models\PromptCategory;
use App\Models\Tag;
use App\Queries\Prompts\PromptLibraryQuery;
use App\Services\Prompts\PromptAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PromptLibraryController
{
    public function index(Request $request, PromptLibraryQuery $query): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'integer'], 'tag' => ['nullable', 'string', 'max:80'], 'sort' => ['nullable', 'in:newest,title,popular']]);
        $visible = app(PromptAccess::class)->visibleTo(Prompt::query(), $request->user());

        return view('portal.prompts.index', [
            'prompts' => $query->handle($request->user(), $filters), 'filters' => $filters,
            'categories' => PromptCategory::where('is_active', true)->whereHas('prompts', fn ($prompts) => $prompts->whereIn('id', (clone $visible)->select('prompts.id')))->with('translations')->orderBy('display_order')->get(),
            'tags' => Tag::whereHas('prompts', fn ($prompts) => $prompts->whereIn('prompts.id', (clone $visible)->select('prompts.id')))->orderBy('display_name')->get(),
        ]);
    }

    public function show(Prompt $prompt): View
    {
        Gate::authorize('view', $prompt);

        $prompt->load(['category.translations', 'tags'])->loadCount('uses')->loadExists([
            'favorites as is_favorite' => fn ($favorites) => $favorites->where('user_id', request()->user()->id),
        ]);

        return view('portal.prompts.show', ['prompt' => $prompt]);
    }

    public function copy(Request $request, Prompt $prompt, RecordPromptCopy $action): JsonResponse
    {
        $action->handle($request->user(), $prompt, $request->all());

        return response()->json(['recorded' => true]);
    }
}
