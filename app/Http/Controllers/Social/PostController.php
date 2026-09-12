<?php

namespace App\Http\Controllers\Social;

use App\Actions\Social\SaveSocialPost;
use App\Models\SocialMediaAccount;
use App\Models\SocialPost;
use App\Queries\Social\SocialAnalyticsQuery;
use App\Services\Social\SocialAccess;
use App\Services\Social\SocialFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostController
{
    public function index(Request $r, SocialMediaAccount $account, SocialAnalyticsQuery $query)
    {
        app(SocialAccess::class)->authorize($r->user(), 'posts.view', $account);
        $f = SocialFilters::make($account, $r->query());
        $posts = $query->postStats($r->user(), $account, $f)->with(['contentType', 'campaign', 'event', 'tags'])->orderByDesc('published_at')->paginate(20)->withQueryString();

        return view('social.posts', compact('account', 'posts', 'f') + ['options' => app(WorkspaceController::class)->options($r, $account)]);
    }

    public function show(Request $r, SocialMediaAccount $account, SocialPost $post, SocialAnalyticsQuery $query)
    {
        abort_unless($post->social_account_id === $account->id, 404);
        app(SocialAccess::class)->post($r->user(), $post, 'posts.view');
        $f = SocialFilters::make($account, $r->query());
        $stats = $query->postStats($r->user(), $account, $f)->where('social_posts.id', $post->id)->first();
        $comparison = [];
        foreach (['all' => [], 'same_type' => ['content_type_id' => $post->content_type_id], 'same_campaign' => ['campaign_id' => $post->campaign_id],
            'same_month' => ['from' => $post->published_at->setTimezone($account->timezone)->startOfMonth()->toDateString(), 'to' => $post->published_at->setTimezone($account->timezone)->endOfMonth()->toDateString()]] as $key => $filter) {
            $comparison[$key] = $key === 'same_campaign' && ! $post->campaign_id ? null : DB::query()->fromSub($query->postStats($r->user(), $account, array_merge($f, $filter))->toBase(), 'p')->avg('engagement_rate');
        }

        return view('social.post', compact('account', 'post', 'stats', 'comparison', 'f'));
    }

    public function create(Request $r, SocialMediaAccount $account)
    {
        app(SocialAccess::class)->authorize($r->user(), 'posts.create', $account);

        return view('social.post-form', ['account' => $account, 'post' => null, 'options' => app(WorkspaceController::class)->options($r, $account)]);
    }

    public function edit(Request $r, SocialMediaAccount $account, SocialPost $post)
    {
        abort_unless($post->social_account_id === $account->id, 404);
        app(SocialAccess::class)->post($r->user(), $post, 'posts.edit');

        return view('social.post-form', ['account' => $account, 'post' => $post, 'options' => app(WorkspaceController::class)->options($r, $account)]);
    }

    public function save(Request $r, SocialMediaAccount $account, SaveSocialPost $action, ?SocialPost $post = null)
    {
        $saved = $action->handle($r->user(), $account, $r->all(), $post);

        return redirect()->route('social.posts.show', [$account, $saved])->with('status', __('social.saved'));
    }

    public function delete(Request $r, SocialMediaAccount $account, SocialPost $post, SaveSocialPost $action)
    {
        abort_unless($post->social_account_id === $account->id, 404);
        $action->delete($r->user(), $post);

        return redirect()->route('social.posts', [$account])->with('status', __('social.saved'));
    }
}
