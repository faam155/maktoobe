<?php

namespace App\Http\Controllers\Social;

use App\Actions\Social\ImportSocialMetrics;
use App\Actions\Social\RefreshSocialAccount;
use App\Actions\Social\SaveSocialMetric;
use App\Models\SocialAccountMetric;
use App\Models\SocialImport;
use App\Models\SocialMediaAccount;
use App\Models\SocialPostMetric;
use App\Models\SocialSyncLog;
use App\Services\Social\SocialAccess;
use Illuminate\Http\Request;

class DataController
{
    public function metrics(Request $r, SocialMediaAccount $account)
    {
        app(SocialAccess::class)->authorize($r->user(), 'metrics.manage', $account);
        $posts = app(SocialAccess::class)->posts($r->user(), $account)->latest('published_at')->limit(500)->get(['id', 'title']);
        $metrics = SocialAccountMetric::where('social_account_id', $account->id)->latest('ends_on')->paginate(20);
        $postMetrics = SocialPostMetric::whereIn('social_post_id', app(SocialAccess::class)->posts($r->user(), $account)->select('social_posts.id'))->latest('ends_on')->paginate(20, ['*'], 'post_page');

        return view('social.metrics', compact('account', 'posts', 'metrics', 'postMetrics'));
    }

    public function metricSave(Request $r, SocialMediaAccount $account, SaveSocialMetric $action)
    {
        $action->handle($r->user(), $account, $r->all());

        return back()->with('status', __('social.saved'));
    }

    public function imports(Request $r, SocialMediaAccount $account)
    {
        app(SocialAccess::class)->authorize($r->user(), 'import', $account);

        return view('social.import', ['account' => $account, 'imports' => SocialImport::where('social_account_id', $account->id)->where('user_id', $r->user()->id)->latest('id')->paginate(10)]);
    }

    public function preview(Request $r, SocialMediaAccount $account, ImportSocialMetrics $action)
    {
        $r->validate(['file' => ['required', 'file']]);
        $action->preview($r->user(), $account, $r->file('file'));

        return back()->with('status', __('social.preview_ready'));
    }

    public function confirm(Request $r, SocialMediaAccount $account, SocialImport $import, ImportSocialMetrics $action)
    {
        $action->confirm($r->user(), $account, $import);

        return back()->with('status', __('social.saved'));
    }

    public function template(Request $r, SocialMediaAccount $account)
    {
        app(SocialAccess::class)->authorize($r->user(), 'import', $account);

        return response(implode(',', ImportSocialMetrics::HEADER)."\r\naccount,,2026-09-01,2026-09-01,1000,30,5,2,15,10000,20\r\n", 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="linkedin-metrics-template.csv"', 'Cache-Control' => 'private, no-store']);
    }

    public function sync(Request $r, SocialMediaAccount $account, RefreshSocialAccount $action)
    {
        $action->handle($r->user(), $account);

        return back()->with('status', __('social.queued'));
    }

    public function logs(Request $r, SocialMediaAccount $account)
    {
        app(SocialAccess::class)->authorize($r->user(), 'sync', $account);

        return view('social.sync', ['account' => $account, 'logs' => SocialSyncLog::where('social_account_id', $account->id)->latest('id')->paginate(20)]);
    }
}
