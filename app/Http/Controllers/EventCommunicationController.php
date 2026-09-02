<?php

namespace App\Http\Controllers;

use App\Actions\Events\GenerateEventCommunication;
use App\Actions\Events\SaveEventCommunication;
use App\Models\Event;
use App\Models\EventCommunication;
use App\Models\EventCommunicationGeneration;
use App\Queries\Events\EventCommunicationQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventCommunicationController
{
    public function index(Request $request, Event $event, EventCommunicationQuery $query)
    {
        return view('events.communications', $query->handle($request->user(), $event, $request->query()) + ['administration' => $request->routeIs('admin.*')]);
    }

    private function destination(Request $request, Event $event, EventCommunication $communication): string
    {
        return route($request->routeIs('admin.*') ? 'admin.events.communications.index' : 'events.communications.index', ['event' => $event, 'type' => $communication->type, 'language' => $communication->language]);
    }

    public function store(Request $request, Event $event, SaveEventCommunication $action)
    {
        $communication = $action->handle($request->user(), $event, $request->all());

        return redirect($this->destination($request, $event, $communication))->with('status', __('communications.saved'));
    }

    public function archive(Request $request, Event $event, EventCommunication $communication, SaveEventCommunication $action)
    {
        $action->archive($request->user(), $event, $communication, $request->all());

        return redirect($this->destination($request, $event, $communication))->with('status', __('communications.archived'));
    }

    public function generate(Request $request, Event $event, GenerateEventCommunication $action)
    {
        $generation = $action->handle($request->user(), $event, $request->all());

        return redirect($this->destination($request, $event, $generation->communication))->with('status', __('communications.queued'));
    }

    public function apply(Request $request, Event $event, EventCommunicationGeneration $generation, SaveEventCommunication $action)
    {
        $action->apply($request->user(), $event, $generation);

        return redirect($this->destination($request, $event, $generation->communication))->with('status', __('communications.saved'));
    }

    public function status(Request $request, Event $event, EventCommunicationGeneration $generation)
    {
        Gate::authorize('generate', [EventCommunication::class, $event]);
        abort_unless($generation->event_id === $event->id && $generation->user_id === $request->user()->id, 404);

        return response()->json(['status' => $generation->status])->header('Cache-Control', 'private, no-store');
    }
}
