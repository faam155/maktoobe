<x-dynamic-component :component="$administration ? 'layouts.admin' : 'layouts.portal'" :title="__('calendar.title')">
@php
    $calendarRoute = $administration ? 'admin.calendar' : 'events.calendar';
    $detailRoute = $administration ? 'admin.events.show' : 'events.show';
    $baseFilters = collect($filters)->except(['page','from','to','date','view'])->all();
    $previous = $view === 'week' ? $anchor->subWeek() : $anchor->subMonthNoOverflow();
    $next = $view === 'week' ? $anchor->addWeek() : $anchor->addMonthNoOverflow();
@endphp
<section class="calendar-heading"><div><p class="portal-eyebrow">{{ __('calendar.intro') }}</p><h2>{{ $view==='month' ? $anchor->translatedFormat('F Y') : $start->translatedFormat('j M Y').' — '.$end->subDay()->translatedFormat('j M Y') }}</h2><p>{{ __('calendar.timezone',['zone'=>$timezone]) }}</p></div><a class="admin-button admin-button-secondary" href="{{ route($administration ? 'admin.events.index':'events.index') }}">{{ __('calendar.back') }}</a></section>
<div class="calendar-toolbar">
    <nav class="calendar-period" aria-label="{{ __('calendar.date') }}"><a href="{{ route($calendarRoute,$baseFilters+['view'=>$view,'date'=>$previous->toDateString()]) }}" aria-label="{{ __('calendar.previous') }}">{{ __('calendar.previous') }}</a><a href="{{ route($calendarRoute,$baseFilters+['view'=>$view]) }}">{{ __('calendar.today') }}</a><a href="{{ route($calendarRoute,$baseFilters+['view'=>$view,'date'=>$next->toDateString()]) }}" aria-label="{{ __('calendar.next') }}">{{ __('calendar.next') }}</a></nav>
    <nav class="calendar-views" aria-label="{{ __('calendar.title') }}">@foreach(['month','week','list'] as $mode)<a href="{{ route($calendarRoute,$baseFilters+['view'=>$mode,'date'=>$anchor->toDateString()]) }}" @class(['is-current'=>$view===$mode]) @if($view===$mode) aria-current="page" @endif>{{ __('calendar.'.$mode) }}</a>@endforeach</nav>
</div>
<details class="calendar-filters" open><summary>{{ __('calendar.filters') }}</summary><form method="get" action="{{ route($calendarRoute) }}"><input type="hidden" name="view" value="{{ $view }}">
    <label>{{ __('calendar.date') }}<input type="date" name="date" value="{{ $anchor->toDateString() }}" required></label>
    <label>{{ __('events.status') }}<select name="status"><option value="">{{ __('calendar.all') }}</option>@foreach(\App\Enums\EventStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status']??'')===$status->value)>{{ __('events.'.$status->value) }}</option>@endforeach</select></label>
    <label>{{ __('events.category') }}<select name="category"><option value="">{{ __('calendar.all') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)($filters['category']??'')===(string)$category->id)>{{ $category->name() }}</option>@endforeach</select></label>
    <label>{{ __('events.organizer') }}<select name="organizer"><option value="">{{ __('calendar.all') }}</option>@foreach($organizers as $organizer)<option value="{{ $organizer->id }}" @selected((string)($filters['organizer']??'')===(string)$organizer->id)>{{ $organizer->name }}</option>@endforeach</select></label>
    @can('manage-events')<label>{{ __('events.visibility') }}<select name="visibility"><option value="">{{ __('calendar.all') }}</option>@foreach(\App\Enums\EventVisibility::cases() as $visibility)<option value="{{ $visibility->value }}" @selected(($filters['visibility']??'')===$visibility->value)>{{ __('events.'.$visibility->value) }}</option>@endforeach</select></label>@endcan
    <label>{{ __('calendar.from') }}<input type="date" name="from" value="{{ $filters['from']??'' }}"></label><label>{{ __('calendar.to') }}<input type="date" name="to" value="{{ $filters['to']??'' }}"></label>
    <div class="calendar-filter-actions"><button class="admin-button">{{ __('calendar.apply') }}</button><a href="{{ route($calendarRoute,['view'=>$view,'date'=>$anchor->toDateString()]) }}">{{ __('calendar.clear') }}</a></div><p>{{ __('calendar.range_help') }}</p>
</form></details>
@if($errors->any() && !$administration)<div role="alert" class="admin-errors">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if($events->total()===0)<p class="calendar-empty">{{ __('calendar.empty') }}</p>@endif
@if($events->hasPages())<p role="status" class="calendar-density">{{ __('calendar.density',['count'=>$events->count(),'total'=>$events->total()]) }}</p>@endif
@if($view!=='list')
<section class="calendar-grid-view calendar-{{ $view }}" aria-label="{{ __('calendar.'.$view) }}">
    <div class="calendar-weekdays" aria-hidden="true">@foreach($days->take(7) as $day)<span>{{ $day['date']->translatedFormat('D') }}</span>@endforeach</div>
    <div class="calendar-grid">@foreach($days as $day)<section @class(['calendar-cell','is-outside'=>$view==='month' && $day['date']->month!==$anchor->month,'is-today'=>$day['date']->isToday()]) aria-label="{{ $day['date']->translatedFormat('l j F Y') }}"><time datetime="{{ $day['date']->toDateString() }}">{{ $day['date']->format('j') }}</time><div>@foreach($day['events'] as $event)<a class="calendar-event" href="{{ route($detailRoute,$event) }}"><span class="calendar-event-time">{{ $event->starts_at->lt($day['date']->utc()) ? __('calendar.continued') : $event->starts_at->copy()->setTimezone($timezone)->format('H:i') }}</span><strong>{{ $event->title }}</strong><span>{{ __('events.'.$event->status->value) }}</span>@if($event->category)<small>{{ $event->category->name() }}</small>@endif @if($event->location)<small>{{ $event->location }}</small>@endif</a>@endforeach</div></section>@endforeach</div>
</section>
@endif
<section @class(['calendar-agenda','is-list'=>$view==='list']) aria-label="{{ __('calendar.list') }}">
    <h3 class="calendar-mobile-label">{{ __('calendar.mobile') }}</h3>
    @foreach($days as $day)@if($day['events']->isNotEmpty())<section class="calendar-agenda-day"><h3><time datetime="{{ $day['date']->toDateString() }}">{{ $day['date']->translatedFormat('l j F') }}</time></h3><div>@foreach($day['events'] as $event)<a class="calendar-agenda-event" href="{{ route($detailRoute,$event) }}"><div><strong>{{ $event->title }}</strong><span>{{ __('events.'.$event->status->value) }} · {{ $event->category?->name() ?? __('events.no_category') }}</span></div><time>{{ $event->starts_at->copy()->setTimezone($timezone)->translatedFormat('j M H:i') }} — {{ $event->ends_at->copy()->setTimezone($timezone)->translatedFormat('j M H:i') }}</time><span>{{ $event->location ?: '—' }}</span></a>@endforeach</div></section>@endif @endforeach
</section>
{{ $events->links() }}
</x-dynamic-component>
