<?php

namespace App\Actions\Prompts;

use App\Actions\Identity\RecordAccountAudit;
use App\Enums\PromptStatus;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangePromptStatus
{
    public function handle(User $actor, Prompt $prompt, PromptStatus $status): Prompt
    {
        Gate::forUser($actor)->authorize($status === PromptStatus::Published ? 'publish' : 'update', $prompt);

        return DB::transaction(function () use ($actor, $prompt, $status) {
            $prompt = Prompt::lockForUpdate()->with(['category', 'allowedUsers', 'allowedRoles'])->findOrFail($prompt->id);
            if ($status === PromptStatus::Published) {
                if ($prompt->category_id && (! $prompt->category || ! $prompt->category->is_active || $prompt->category->trashed())) {
                    throw ValidationException::withMessages(['category_id' => __('prompts.category_must_be_active')]);
                }
                if ($prompt->visibility->value === 'selected_users' && $prompt->allowedUsers->isEmpty()) {
                    throw ValidationException::withMessages(['user_ids' => __('prompts.audience_required')]);
                }
                if ($prompt->visibility->value === 'selected_roles' && $prompt->allowedRoles->isEmpty()) {
                    throw ValidationException::withMessages(['role_ids' => __('prompts.audience_required')]);
                }
            }
            $prompt->update([
                'status' => $status,
                'published_at' => $status === PromptStatus::Published ? now() : null,
                'published_by' => $status === PromptStatus::Published ? $actor->id : null,
            ]);
            app(RecordAccountAudit::class)->handle($actor, 'prompt.status_changed', ['prompt_id' => $prompt->id, 'status' => $status->value], $actor);

            return $prompt;
        });
    }
}
