@php($eventRoutePrefix = request()->routeIs('admin.*') ? 'admin.events' : 'events')
<nav class="event-workspace-tabs" aria-label="{{ __('events.workspace') }}">
    <a @class(['is-current'=>request()->routeIs($eventRoutePrefix.'.show')]) href="{{ route($eventRoutePrefix.'.show',$event) }}">{{ __('events.overview') }}</a>
    <a @class(['is-current'=>request()->routeIs($eventRoutePrefix.'.files.*') && request('category')==='photos']) href="{{ route($eventRoutePrefix.'.files.index',['event'=>$event,'category'=>'photos']) }}">{{ __('events.photos') }}</a>
    <a @class(['is-current'=>request()->routeIs($eventRoutePrefix.'.files.*') && request('category')!=='photos']) href="{{ route($eventRoutePrefix.'.files.index',$event) }}">{{ __('events.documents') }}</a>
    @foreach(['reports','communications','activity'] as $tab)<span aria-disabled="true" title="{{ __('events.coming_later') }}">{{ __('events.'.$tab) }}</span>@endforeach
</nav>
