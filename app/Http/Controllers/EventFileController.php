<?php

namespace App\Http\Controllers;

use App\Actions\Events\DeleteEventFile;
use App\Actions\Events\UpdateEventFile;
use App\Actions\Events\UploadEventFiles;
use App\Models\Event;
use App\Models\EventFile;
use App\Queries\Events\EventFileIndexQuery;
use App\Services\Events\EventFileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventFileController
{
    public function index(Request $request, Event $event, EventFileIndexQuery $query)
    {
        return view('events.files', $query->handle($request->user(), $event, $request->query()) + ['administration' => $request->routeIs('admin.*')]);
    }

    public function store(Request $request, Event $event, UploadEventFiles $action)
    {
        $action->handle($request->user(), $event, $request->all());
        $url = route($request->routeIs('admin.*') ? 'admin.events.files.index' : 'events.files.index', ['event' => $event, 'category' => $request->input('category')]);
        $request->session()->flash('status', __('event_files.uploaded'));

        return $request->expectsJson() ? response()->json(['redirect' => $url]) : redirect($url);
    }

    public function update(Request $request, Event $event, EventFile $file, UpdateEventFile $action)
    {
        $this->parent($event, $file);
        $action->handle($request->user(), $file, $request->all());

        return back()->with('status', __('event_files.updated'));
    }

    public function destroy(Request $request, Event $event, EventFile $file, DeleteEventFile $action)
    {
        $this->parent($event, $file);
        Gate::authorize('delete', $file);
        $request->validate(['confirm' => ['accepted']]);
        $action->handle($request->user(), $file);

        return back()->with('status', __('event_files.deleted'));
    }

    public function download(Event $event, EventFile $file)
    {
        return $this->serve($event, $file, false);
    }

    public function preview(Event $event, EventFile $file)
    {
        return $this->serve($event, $file, true);
    }

    private function parent(Event $event, EventFile $file): void
    {
        abort_unless($file->event_id === $event->id, 404);
    }

    private function serve(Event $event, EventFile $file, bool $preview)
    {
        $this->parent($event, $file);

        return app(EventFileResponse::class)->handle(auth()->user(), $file, $preview);
    }
}
