<?php

namespace App\Http\Controllers\Social;

use App\Models\SocialMediaAccount;
use App\Models\SocialSyncLog;
use App\Queries\Social\PublishingQuery;
use App\Queries\Social\SocialAnalyticsQuery;
use App\Services\Social\SocialAccess;
use App\Services\Social\SocialFilters;
use Illuminate\Http\Request;

class DashboardController
{
    public function index(Request $r)
    {
        app(SocialAccess::class)->authorize($r->user(), 'dashboard.view');
        $accounts = SocialMediaAccount::whereHas('platform', fn ($q) => $q->where('slug', 'linkedin')->where('active', true))->where('active', true)->get();

        return view('social.index', compact('accounts'));
    }

    public function show(Request $r, SocialMediaAccount $account, SocialAnalyticsQuery $query)
    {
        app(SocialAccess::class)->authorize($r->user(), 'dashboard.view', $account);
        $f = SocialFilters::make($account, $r->query());
        $data = $query->get($r->user(), $account, $f);
        $data['publicationTrend'] = app(PublishingQuery::class)->get($r->user(), $account, $f)['trend'];

        return view('social.dashboard', $data + ['options' => app(WorkspaceController::class)->options($r, $account), 'sync' => SocialSyncLog::where('social_account_id', $account->id)->latest('id')->first()]);
    }
}
