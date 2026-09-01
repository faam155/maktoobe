<?php

namespace App\Http\Controllers\Admin;

use App\Actions\PromptCategories\CreatePromptCategory;
use App\Actions\PromptCategories\DeletePromptCategory;
use App\Actions\PromptCategories\ReorderPromptCategory;
use App\Actions\PromptCategories\SetPromptCategoryStatus;
use App\Actions\PromptCategories\UpdatePromptCategory;
use App\Models\PromptCategory;
use App\Queries\PromptCategories\PromptCategoryIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PromptCategoryController
{
    public function index(Request $request, PromptCategoryIndexQuery $query): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,inactive']]);

        return view('admin.prompt-categories.index', ['categories' => $query->handle($request->user(), $filters), 'filters' => $filters]);
    }

    public function create(): View
    {
        Gate::authorize('create', PromptCategory::class);

        return view('admin.prompt-categories.create');
    }

    public function store(Request $request, CreatePromptCategory $action): RedirectResponse
    {
        $category = $action->handle($request->user(), $request->all());

        return redirect()->route('admin.prompt-categories.edit', $category)->with('status', __('categories.created'));
    }

    public function edit(PromptCategory $promptCategory): View
    {
        Gate::authorize('update', $promptCategory);

        return view('admin.prompt-categories.edit', ['category' => $promptCategory->load('translations')]);
    }

    public function update(Request $request, PromptCategory $promptCategory, UpdatePromptCategory $action): RedirectResponse
    {
        $action->handle($request->user(), $promptCategory, $request->all());

        return back()->with('status', __('categories.updated'));
    }

    public function status(Request $request, PromptCategory $promptCategory, SetPromptCategoryStatus $action): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $action->handle($request->user(), $promptCategory, (bool) $data['is_active']);

        return back()->with('status', __('categories.status_updated'));
    }

    public function move(Request $request, PromptCategory $promptCategory, ReorderPromptCategory $action): RedirectResponse
    {
        $action->handle($request->user(), $promptCategory, (string) $request->input('direction'));

        return back()->with('status', __('categories.reordered'));
    }

    public function destroy(Request $request, PromptCategory $promptCategory, DeletePromptCategory $action): RedirectResponse
    {
        $action->handle($request->user(), $promptCategory);

        return redirect()->route('admin.prompt-categories.index')->with('status', __('categories.deleted'));
    }
}
