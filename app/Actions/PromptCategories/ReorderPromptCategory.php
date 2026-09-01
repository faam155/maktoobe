<?php

namespace App\Actions\PromptCategories;

use App\Actions\Identity\RecordAccountAudit;
use App\Models\PromptCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ReorderPromptCategory
{
    public function handle(User $actor, PromptCategory $category, string $direction): void
    {
        Gate::forUser($actor)->authorize('update', $category);
        $direction = Validator::make(['direction' => $direction], ['direction' => ['required', 'in:up,down']])->validate()['direction'];

        DB::transaction(function () use ($actor, $category, $direction) {
            $category = PromptCategory::lockForUpdate()->findOrFail($category->id);
            $neighbor = PromptCategory::lockForUpdate()
                ->when($direction === 'up', fn ($query) => $query->where('display_order', '<', $category->display_order)->orderByDesc('display_order'), fn ($query) => $query->where('display_order', '>', $category->display_order)->orderBy('display_order'))
                ->orderBy($direction === 'up' ? 'id' : 'id', $direction === 'up' ? 'desc' : 'asc')->first();
            if (! $neighbor) {
                return;
            }
            [$currentOrder, $neighborOrder] = [$category->display_order, $neighbor->display_order];
            $category->update(['display_order' => $neighborOrder]);
            $neighbor->update(['display_order' => $currentOrder]);
            app(RecordAccountAudit::class)->handle($actor, 'prompt_category.reordered', ['category_id' => $category->id, 'direction' => $direction], $actor);
        });
    }
}
