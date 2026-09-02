<?php

namespace App\Services\Events;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class EventAccess
{
    public function visibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('manage-events')) {
            return $query;
        }

        $roleIds = $user->roles()->pluck('roles.id');

        return $query->where(function (Builder $query) use ($user, $roleIds) {
            $query->where(fn (Builder $owned) => $owned->where('organizer_id', $user->id)->orWhere('created_by', $user->id))
                ->orWhere(function (Builder $visible) use ($user, $roleIds) {
                    $visible->where('status', '!=', EventStatus::Draft->value)
                        ->where(function (Builder $audience) use ($user, $roleIds) {
                            $audience->where('visibility', EventVisibility::AllUsers->value)
                                ->orWhere(fn (Builder $selected) => $selected->where('visibility', EventVisibility::SelectedUsers->value)->whereHas('allowedUsers', fn (Builder $users) => $users->where('users.id', $user->id)))
                                ->orWhere(fn (Builder $selected) => $selected->where('visibility', EventVisibility::SelectedRoles->value)->whereHas('allowedRoles', fn (Builder $roles) => $roles->whereIn('roles.id', $roleIds)));
                        });
                });
        });
    }

    public function canView(User $user, Event $event): bool
    {
        return $this->visibleTo(Event::query()->whereKey($event->id), $user)->exists();
    }
}
