<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\SendSystemNotice;
use App\Queries\Notifications\NotificationInbox;
use Illuminate\Http\Request;

class NotificationController
{
    public function index(Request $request, NotificationInbox $inbox)
    {
        $data = $request->validate(['filter' => ['nullable', 'in:all,unread'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $query = $inbox->query($request->user());
        if (($data['filter'] ?? 'all') === 'unread') {
            $query->whereNull('read_at');
        }

        return view('portal.notifications', ['notifications' => $query->with(['notice.event', 'notice.prompt'])->latest()->paginate(20)->withQueryString(), 'unread' => $inbox->unread($request->user()), 'inbox' => $inbox]);
    }

    public function read(Request $request, string $notification, NotificationInbox $inbox)
    {
        $inbox->query($request->user())->whereKey($notification)->firstOrFail()->markAsRead();

        return back()->with('status', __('notifications.updated'));
    }

    public function readAll(Request $request, NotificationInbox $inbox)
    {
        $inbox->query($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', __('notifications.updated'));
    }

    public function dismiss(Request $request, string $notification, NotificationInbox $inbox)
    {
        $inbox->query($request->user())->whereKey($notification)->firstOrFail()->update(['dismissed_at' => now()]);

        return back()->with('status', __('notifications.updated'));
    }

    public function open(Request $request, string $notification, NotificationInbox $inbox)
    {
        $notification = $inbox->query($request->user())->with(['notice.event', 'notice.prompt', 'notice.reportVersion'])->whereKey($notification)->firstOrFail();
        $notice = $notification->notice;
        $notification->markAsRead();
        if ($notice->kind === 'report_uploaded') {
            return redirect(route('events.reports.index', $notice->event).'#'.$notice->reportVersion->report->type->value);
        }
        if ($notice->event) {
            return redirect()->route('events.show', $notice->event);
        }
        if ($notice->prompt) {
            return redirect()->route('prompts.show', $notice->prompt);
        }

        return redirect()->route('notifications.index');
    }

    public function system(Request $request, SendSystemNotice $action)
    {
        $action->handle($request->user(), $request->all());

        return redirect()->route('notifications.index')->with('status', __('notifications.queued'));
    }
}
