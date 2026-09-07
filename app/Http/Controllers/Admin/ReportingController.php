<?php

namespace App\Http\Controllers\Admin;

use App\Queries\Analytics\AnalyticsQuery;
use App\Queries\Audit\AuditQuery;
use App\Support\Reporting\ReportPeriod;
use Illuminate\Http\Request;

class ReportingController
{
    public function analytics(Request $request, AnalyticsQuery $query)
    {
        $period = ReportPeriod::from($request->user(), $request->all());

        return view('admin.reporting.analytics', $query->get($request->user(), $period) + ['period' => $period]);
    }

    public function audits(Request $request, AuditQuery $query)
    {
        $period = ReportPeriod::from($request->user(), $request->all());

        return view('admin.reporting.audits', $query->get($request->user(), $period, $request->all()) + ['period' => $period]);
    }
}
