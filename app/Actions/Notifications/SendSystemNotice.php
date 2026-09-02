<?php

namespace App\Actions\Notifications;

use App\Actions\Identity\RecordAccountAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SendSystemNotice
{
    public function handle(User $actor, array $input): void
    {
        Gate::forUser($actor)->authorize('manage-system-settings');
        abort_unless($actor->status->value === 'active' && $actor->hasVerifiedEmail(), 403);
        $data = Validator::make($input, [
            'title_en' => ['required', 'string', 'max:120'],
            'title_ar' => ['required', 'string', 'max:120'],
            'body_en' => ['required', 'string', 'max:2000'],
            'body_ar' => ['required', 'string', 'max:2000'],
            'target_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'operation_id' => ['required', 'uuid'],
            'confirm' => ['accepted'],
        ])->validate();
        DB::transaction(function () use ($actor, $data) {
            $notice = app(RecordWorkspaceNotice::class)->handle('system', 'system:'.$actor->id.':'.$data['operation_id'], [
                'created_by' => $actor->id,
                'target_user_id' => $data['target_user_id'] ?? null,
                'broadcast' => empty($data['target_user_id']),
                'system_content' => [
                    'en' => ['title' => $data['title_en'], 'body' => $data['body_en']],
                    'ar' => ['title' => $data['title_ar'], 'body' => $data['body_ar']],
                ],
            ]);
            if ($notice->wasRecentlyCreated) {
                app(RecordAccountAudit::class)->handle($actor, 'notification.system_queued', ['notice_id' => $notice->id], $actor);
            }
        });
    }
}
