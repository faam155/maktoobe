<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Events\ChangeEventStatus;
use App\Actions\Events\DeleteEvent;
use App\Actions\Events\DuplicateEvent;
use App\Actions\Events\SaveEvent;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\User;
use App\Queries\Events\AdminEventIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class EventController
{
    public function index(Request $request, AdminEventIndexQuery $query): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'in:'.collect(EventStatus::cases())->pluck('value')->join(',')]]);

        return view('admin.events.index', ['events' => $query->handle($request->user(), $filters), 'filters' => $filters]);
    }

    public function create(): View
    {
        Gate::authorize('create', Event::class);

        return view('admin.events.create', $this->formData());
    }

    public function store(Request $request, SaveEvent $action): RedirectResponse
    {
        $event = $action->handle($request->user(), $request->all());

        return redirect()->route('admin.events.show', $event)->with('status', __('events.created'));
    }

    public function show(Event $event): View
    {
        Gate::authorize('view', $event);

        return view('admin.events.show', ['event' => $event->load(['category.translations', 'organizer', 'allowedUsers:id,name,email', 'allowedRoles:id,name'])]);
    }

    public function edit(Event $event): View
    {
        Gate::authorize('update', $event);

        return view('admin.events.edit', array_merge($this->formData(), ['event' => $event->load(['allowedUsers:id', 'allowedRoles:id'])]));
    }

    public function update(Request $request, Event $event, SaveEvent $action): RedirectResponse
    {
        $event = $action->handle($request->user(), $request->all(), $event);

        return redirect()->route('admin.events.show', $event)->with('status', __('events.updated'));
    }

    public function status(Request $request, Event $event, ChangeEventStatus $action): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'string', 'in:'.collect(EventStatus::cases())->pluck('value')->join(',')]]);
        $action->handle($request->user(), $event, EventStatus::from($data['status']));

        return back()->with('status', __('events.status_updated'));
    }

    public function duplicate(Request $request, Event $event, DuplicateEvent $action): RedirectResponse
    {
        $copy = $action->handle($request->user(), $event);

        return redirect()->route('admin.events.edit', $copy)->with('status', __('events.duplicated'));
    }

    public function destroy(Request $request, Event $event, DeleteEvent $action): RedirectResponse
    {
        $action->handle($request->user(), $event);

        return redirect()->route('admin.events.index')->with('status', __('events.deleted'));
    }

    private function formData(): array
    {
        return ['categories' => EventCategory::where('is_active', true)->with('translations')->orderBy('display_order')->get(), 'users' => User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']), 'roles' => Role::where('guard_name', 'web')->orderBy('name')->get(['id', 'name']), 'statuses' => EventStatus::cases(), 'visibilities' => EventVisibility::cases()];
    }
}
