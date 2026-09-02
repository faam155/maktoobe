<?php

namespace App\Http\Controllers\Portal;

use App\Models\Event;
use App\Queries\Events\PortalEventIndexQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EventController
{
    public function index(Request $request, PortalEventIndexQuery $query): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'period' => ['nullable', 'in:upcoming,past,all']]);

        return view('portal.events.index', ['events' => $query->handle($request->user(), $filters), 'filters' => $filters]);
    }

    public function show(Event $event): View
    {
        Gate::authorize('view', $event);

        return view('portal.events.show', ['event' => $event->load(['category.translations', 'organizer:id,name'])]);
    }
}
