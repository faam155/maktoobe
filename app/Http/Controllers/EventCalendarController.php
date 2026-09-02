<?php

namespace App\Http\Controllers;

use App\Queries\Events\EventCalendarQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventCalendarController
{
    public function __invoke(Request $request, EventCalendarQuery $query): View
    {
        return view('events.calendar', $query->handle($request->user(), $request->query(), $request->routeIs('admin.*')));
    }
}
