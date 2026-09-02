<?php

namespace App\Http\Controllers;

use App\Actions\Events\DeleteEventReport;
use App\Actions\Events\UploadEventReport;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\EventReportVersion;
use App\Queries\Events\EventReportIndexQuery;
use App\Services\Events\EventFileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventReportController
{
    public function index(Request $request, Event $event, EventReportIndexQuery $query)
    {
        return view('events.reports', $query->handle($request->user(), $event, $request->query()) + ['administration' => $request->routeIs('admin.*')]);
    }

    public function store(Request $request, Event $event, UploadEventReport $action)
    {
        $action->handle($request->user(), $event, $request->all());
        $url = route($request->routeIs('admin.*') ? 'admin.events.reports.index' : 'events.reports.index', $event).'#'.$request->input('type');
        $request->session()->flash('status', __('event_reports.uploaded'));

        return $request->expectsJson() ? response()->json(['redirect' => $url]) : redirect($url);
    }

    public function destroy(Request $request, Event $event, EventReport $report, DeleteEventReport $action)
    {
        abort_unless($report->event_id === $event->id, 404);
        Gate::authorize('delete', $report);
        $request->validate(['confirm' => ['accepted']]);
        $action->handle($request->user(), $report);

        return back()->with('status', __('event_reports.deleted'));
    }

    public function download(Request $request, Event $event, EventReport $report, EventReportVersion $version, EventFileResponse $response)
    {
        abort_unless($report->event_id === $event->id && $version->event_report_id === $report->id && $version->event_id === $event->id, 404);
        Gate::authorize('view', $report);
        abort_unless($version->file, 404);

        return $response->handle($request->user(), $version->file);
    }
}
