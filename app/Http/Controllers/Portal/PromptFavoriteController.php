<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Prompts\AddPromptFavorite;
use App\Actions\Prompts\RemovePromptFavorite;
use App\Models\Prompt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PromptFavoriteController
{
    public function store(Request $request, Prompt $prompt, AddPromptFavorite $action): RedirectResponse
    {
        $action->handle($request->user(), $prompt);

        return back()->with('status', __('prompts.favorite_added'));
    }

    public function destroy(Request $request, Prompt $prompt, RemovePromptFavorite $action): RedirectResponse
    {
        $action->handle($request->user(), $prompt);

        return back()->with('status', __('prompts.favorite_removed'));
    }
}
