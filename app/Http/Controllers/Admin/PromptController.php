<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Prompts\ChangePromptStatus;
use App\Actions\Prompts\CreatePrompt;
use App\Actions\Prompts\DeletePrompt;
use App\Actions\Prompts\DuplicatePrompt;
use App\Actions\Prompts\UpdatePrompt;
use App\Enums\PromptStatus;
use App\Models\Prompt;
use App\Models\PromptCategory;
use App\Models\User;
use App\Queries\Prompts\AdminPromptIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class PromptController
{
    public function index(Request $request, AdminPromptIndexQuery $query): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:draft,published,archived'], 'category' => ['nullable', 'integer']]);

        return view('admin.prompts.index', ['prompts' => $query->handle($request->user(), $filters), 'filters' => $filters, 'categories' => $this->categories()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Prompt::class);

        return view('admin.prompts.create', $this->formData());
    }

    public function store(Request $request, CreatePrompt $action): RedirectResponse
    {
        $prompt = $action->handle($request->user(), $request->all());

        return redirect()->route('admin.prompts.edit', $prompt)->with('status', __('prompts.created'));
    }

    public function show(Prompt $prompt): View
    {
        Gate::authorize('view', $prompt);

        return view('admin.prompts.show', ['prompt' => $prompt->load(['category.translations', 'tags', 'allowedUsers:id,name,email', 'allowedRoles:id,name', 'owner:id,name'])->loadCount('uses')]);
    }

    public function edit(Prompt $prompt): View
    {
        Gate::authorize('update', $prompt);

        return view('admin.prompts.edit', array_merge($this->formData(), ['prompt' => $prompt->load(['tags', 'allowedUsers:id', 'allowedRoles:id'])]));
    }

    public function update(Request $request, Prompt $prompt, UpdatePrompt $action): RedirectResponse
    {
        $prompt = $action->handle($request->user(), $prompt, $request->all());

        return redirect()->route('admin.prompts.edit', $prompt)->with('status', __('prompts.updated_draft'));
    }

    public function status(Request $request, Prompt $prompt, ChangePromptStatus $action): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:draft,published,archived']]);
        $action->handle($request->user(), $prompt, PromptStatus::from($data['status']));

        return back()->with('status', __('prompts.status_updated'));
    }

    public function duplicate(Request $request, Prompt $prompt, DuplicatePrompt $action): RedirectResponse
    {
        $copy = $action->handle($request->user(), $prompt);

        return redirect()->route('admin.prompts.edit', $copy)->with('status', __('prompts.duplicated'));
    }

    public function destroy(Request $request, Prompt $prompt, DeletePrompt $action): RedirectResponse
    {
        $action->handle($request->user(), $prompt);

        return redirect()->route('admin.prompts.index')->with('status', __('prompts.deleted'));
    }

    private function categories()
    {
        return PromptCategory::where('is_active', true)->with('translations')->orderBy('display_order')->get();
    }

    private function formData(): array
    {
        return [
            'categories' => $this->categories(),
            'users' => User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']),
            'roles' => Role::where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
        ];
    }
}
