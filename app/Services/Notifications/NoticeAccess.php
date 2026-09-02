<?php

namespace App\Services\Notifications;

use App\Enums\AccountStatus;
use App\Models\Event;
use App\Models\Prompt;
use App\Models\User;
use App\Models\WorkspaceNotice;
use App\Services\Events\EventAccess;
use App\Services\Prompts\PromptAccess;
use Illuminate\Database\Eloquent\Builder;

class NoticeAccess
{
    public function visibleTo(Builder $query, User $user): Builder
    {
        if ($user->status !== AccountStatus::Active || ! $user->hasVerifiedEmail() || $user->trashed()) {
            return $query->whereRaw('1=0');
        }
        $events = app(EventAccess::class)->visibleTo(Event::query(), $user)->select('events.id');
        $prompts = app(PromptAccess::class)->visibleTo(Prompt::query(), $user)->select('prompts.id');

        return $query->where(fn ($q) => $q->where('broadcast', true)->orWhere('target_user_id', $user->id))
            ->where(function ($q) use ($events, $prompts) {
                $q->where('kind', 'system')
                    ->orWhere(fn ($p) => $p->where('kind', 'prompt_published')->whereIn('prompt_id', $prompts))
                    ->orWhere(function ($e) use ($events) {
                        $e->whereIn('event_id', $events)->whereIn('kind', ['event_published', 'event_updated', 'event_cancelled', 'event_assigned', 'event_reminder', 'report_uploaded'])
                            ->whereHas('event', fn ($event) => $event->where('status', '!=', 'draft'))
                            ->where(fn ($r) => $r->where('kind', '!=', 'report_uploaded')->orWhereHas('reportVersion', fn ($v) => $v->whereHas('report')->whereHas('file', fn ($f) => $f->where('scan_status', 'clean'))))
                            ->where(fn ($r) => $r->where('kind', '!=', 'event_reminder')->orWhereHas('event', fn ($event) => $event->whereIn('status', ['planned', 'confirmed'])->where('starts_at', '>', now())->whereColumn('events.starts_at', 'workspace_notices.occurrence_at')));
                    });
            });
    }

    public function canView(User $user, WorkspaceNotice $notice): bool
    {
        return $this->visibleTo(WorkspaceNotice::whereKey($notice->id), $user)->exists();
    }
}
