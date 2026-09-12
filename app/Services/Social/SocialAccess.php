<?php

namespace App\Services\Social;

use App\Models\Event;
use App\Models\SocialMediaAccount;
use App\Models\SocialPost;
use App\Models\User;
use App\Services\Events\EventAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class SocialAccess
{
    public function authorize(User $actor, string $ability, ?SocialMediaAccount $account = null): void
    {
        abort_unless($actor->status->value === 'active' && $actor->hasVerifiedEmail() && ! $actor->trashed(), 403);
        Gate::forUser($actor)->authorize('linkedin.'.$ability);
        if ($account) {
            abort_unless(! $account->trashed() && $account->active && $account->platform->slug === 'linkedin' && $account->platform->active, 404);
        }
    }

    public function posts(User $actor, SocialMediaAccount $account): Builder
    {
        return SocialPost::where('social_account_id', $account->id)->where(function (Builder $q) use ($actor) {
            $q->whereNull('event_id')->orWhereIn('event_id', app(EventAccess::class)->visibleTo(Event::query(), $actor)->select('events.id'));
        });
    }

    public function post(User $actor, SocialPost $post, string $ability): void
    {
        $this->authorize($actor, $ability, $post->account);
        abort_unless($this->posts($actor, $post->account)->whereKey($post->id)->exists(), 404);
    }
}
