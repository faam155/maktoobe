<?php

namespace App\Http\Controllers\Social;

use App\Actions\Social\GenerateSocialInsight;
use App\Actions\Social\SaveSocialReport;
use App\Http\Requests\SocialReportRequest;
use App\Models\SocialAiInsight;
use App\Models\SocialMediaAccount;
use App\Models\SocialReport;
use App\Queries\Social\SocialAnalyticsQuery;
use App\Services\Social\SocialAccess;
use App\Services\Social\SocialExport;
use App\Services\Social\SocialFilters;
use App\Services\Social\SocialProvenance;
use Illuminate\Http\Request;

class ReportController
{
    private function data(Request $r, SocialMediaAccount $account): array
    {
        app(SocialAccess::class)->authorize($r->user(), 'export', $account);
        $f = SocialFilters::make($account, $r->query());
        $data = app(SocialAnalyticsQuery::class)->get($r->user(), $account, $f);
        $fingerprint = app(SocialProvenance::class)->fingerprint($r->user(), $account);
        $data['insights'] = $r->user()->can('linkedin.ai.view') ? SocialAiInsight::where('social_account_id', $account->id)->where('user_id', $r->user()->id)->latest('id')->limit(20)->get()->filter(fn ($i) => $i->filters === $f && hash_equals($i->snapshot['visibility'], $fingerprint)) : collect();
        $data['options'] = app(WorkspaceController::class)->options($r, $account);

        return $data;
    }

    public function index(Request $r, SocialMediaAccount $account)
    {
        return view('social.reports', $this->data($r, $account) + ['reports' => SocialReport::where('social_account_id', $account->id)->where('user_id', $r->user()->id)->latest('id')->paginate(10)]);
    }

    public function save(SocialReportRequest $r, SocialMediaAccount $account)
    {
        $data = $this->data($r, $account);
        app(SaveSocialReport::class)->handle($r->user(), $account, $r->validated(), $data);

        return back()->with('status', __('social.saved'));
    }

    public function export(Request $r, SocialMediaAccount $account, string $format, SocialExport $export)
    {
        $reportId = $r->validate(['report_id' => ['nullable', 'integer', 'min:1']])['report_id'] ?? null;
        $report = null;
        if ($reportId) {
            $report = SocialReport::where('social_account_id', $account->id)->where('user_id', $r->user()->id)->findOrFail($reportId);
            abort_unless(hash_equals($report->filters['_visibility'], app(SocialProvenance::class)->fingerprint($r->user(), $account)), 403);
            $r->query->replace(collect($report->filters)->except('_visibility')->all());
        }
        $data = $this->data($r, $account);
        if ($report) {
            $data['summary'] = $report->summary;
        }

        return $export->download($data, $format);
    }

    public function generate(Request $r, SocialMediaAccount $account, GenerateSocialInsight $action)
    {
        $action->handle($r->user(), $account, SocialFilters::make($account, $r->query()), $r->all());

        return back()->with('status', __('social.queued'));
    }
}
