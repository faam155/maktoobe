<?php

namespace App\Http\Controllers;

use App\Actions\Events\DeleteEventFile;
use App\Actions\Events\UpdateEventFile;
use App\Actions\Events\UploadEventFiles;
use App\Models\Event;
use App\Models\EventFile;
use App\Queries\Events\EventFileIndexQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

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
        Gate::authorize('view', $file);
        abort_if($preview && ! $file->isImage(), 404);
        abort_unless($file->storage_disk === 'local' && preg_match('~^event-files/'.$event->id.'/[a-f0-9-]{36}\.(png|jpe?g|webp|pdf|docx|txt)$~D', $file->storage_path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($file->storage_path), 404);
        $headers = ['Content-Type' => $file->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; sandbox", 'Referrer-Policy' => 'no-referrer'];

        return $disk->response($file->storage_path, $file->original_name, $headers, $preview ? 'inline' : 'attachment');
    }
}
