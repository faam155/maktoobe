<?php

namespace App\Actions\Social;

use App\Actions\Audit\RecordAudit;
use App\Models\Event;
use App\Models\SocialCampaign;
use App\Models\SocialMediaAccount;
use App\Models\SocialPost;
use App\Models\SocialTag;
use App\Models\User;
use App\Services\Events\EventAccess;
use App\Services\Social\SocialAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveSocialPost
{
    public function handle(User $actor, SocialMediaAccount $account, array $input, ?SocialPost $post = null): SocialPost
    {
        app(SocialAccess::class)->authorize($actor, $post ? 'posts.edit' : 'posts.create', $account);
        if ($post) {
            app(SocialAccess::class)->post($actor, $post, 'posts.edit');
            abort_unless($post->social_account_id === $account->id, 404);
        }
        $v = Validator::make($input, [
            'platform_post_id' => ['required', 'string', 'max:180', Rule::unique('social_posts')->where('social_account_id', $account->id)->ignore($post?->id)],
            'title' => ['required', 'string', 'max:200'], 'content' => ['nullable', 'string', 'max:20000'],
            'url' => ['nullable', 'url:https', 'max:500'], 'thumbnail_url' => ['nullable', 'url:https', 'max:500'],
            'published_at' => ['required', 'date_format:Y-m-d\TH:i'], 'content_type_id' => ['required', 'integer', 'exists:social_content_types,id'],
            'language' => ['required', 'in:ar,en,bilingual,other'], 'campaign_id' => ['nullable', 'integer'],
            'event_id' => ['nullable', 'integer'], 'status' => ['required', 'in:published,archived'],
            'tags' => ['nullable', 'array', 'max:30'], 'tags.*' => ['integer', 'distinct'],
            'revision' => [$post ? 'required' : 'nullable', 'integer', 'min:1'],
        ])->validate();

        return DB::transaction(function () use ($actor, $account, $post, $v) {
            SocialMediaAccount::lockForUpdate()->findOrFail($account->id);
            if (filled($v['event_id'] ?? null)) {
                abort_unless(app(EventAccess::class)->visibleTo(Event::query(), $actor)->whereKey($v['event_id'])->lockForUpdate()->exists(), 404);
            }
            if (filled($v['campaign_id'] ?? null)) {
                abort_unless(SocialCampaign::where('social_account_id', $account->id)->whereKey($v['campaign_id'])->exists(), 404);
            }
            $tags = $v['tags'] ?? [];
            abort_unless(SocialTag::where('social_account_id', $account->id)->whereIn('id', $tags)->count() === count($tags), 404);
            if ($post) {
                $post = SocialPost::lockForUpdate()->findOrFail($post->id);
                app(SocialAccess::class)->post($actor, $post, 'posts.edit');
                abort_unless($post->revision === (int) $v['revision'], 409);
            }
            $data = collect($v)->except(['tags', 'revision'])->all();
            $data['published_at'] = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $v['published_at'], $account->timezone)->utc();
            $data['updated_by'] = $actor->id;
            $data['revision'] = ($post?->revision ?? 0) + 1;
            if ($post) {
                $post->update($data);
            } else {
                $post = SocialPost::create($data + ['social_account_id' => $account->id, 'created_by' => $actor->id]);
            }
            $post->tags()->sync($tags);
            app(RecordAudit::class)->handle($actor->id, 'social.post.saved', 'social', $account->id, ['event_id' => $post->event_id, 'revision' => $post->revision]);

            return $post;
        });
    }

    public function delete(User $actor, SocialPost $post): void
    {
        DB::transaction(function () use ($actor, $post) {
            SocialMediaAccount::lockForUpdate()->findOrFail($post->social_account_id);
            $post = SocialPost::lockForUpdate()->findOrFail($post->id);
            app(SocialAccess::class)->post($actor, $post, 'posts.delete');
            $post->delete();
            app(RecordAudit::class)->handle($actor->id, 'social.post.deleted', 'social', $post->social_account_id);
        });
    }
}
