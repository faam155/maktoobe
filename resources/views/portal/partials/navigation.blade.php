<a href="{{ route('account.home') }}" @class(['portal-nav-link','is-current'=>request()->routeIs('account.home')])>{{ __('dashboard.dashboard') }}</a>
@can('use-ai')<a href="{{ route('ai.index') }}" @class(['portal-nav-link','is-current'=>request()->routeIs('ai.*')])>{{ __('dashboard.ai_assistant') }}</a>@endcan
<a href="{{ route('prompts.index') }}" @class(['portal-nav-link','is-current'=>request()->routeIs('prompts.*')])>{{ __('dashboard.prompt_library') }}</a>
<a href="{{ route('my-prompts.index') }}" @class(['portal-nav-link','is-current'=>request()->routeIs('my-prompts.*')])>{{ __('dashboard.my_prompts') }}</a>
<a href="{{ route('events.index') }}" @class(['portal-nav-link','is-current'=>request()->routeIs('events.*') && !request()->routeIs('events.calendar')])>{{ __('dashboard.events') }}</a>
<a href="{{ route('events.calendar') }}" @class(['portal-nav-link','is-current'=>request()->routeIs('events.calendar')])>{{ __('calendar.title') }}</a>
<span class="portal-nav-link is-disabled" aria-disabled="true"><span>{{ __('dashboard.notifications') }}</span><small>{{ __('dashboard.coming_later') }}</small></span>
<a href="{{ route('account.security') }}" @class(['portal-nav-link','is-current'=>request()->routeIs('account.*') && !request()->routeIs('account.home')])>{{ __('dashboard.profile') }}</a>
@can('access-admin')<a href="{{ route('admin.dashboard') }}" class="portal-nav-link">{{ __('dashboard.administration') }}</a>@endcan
@if(auth()->user()->can('manage-events') && !auth()->user()->can('access-admin'))<a href="{{ route('admin.events.index') }}" class="portal-nav-link">{{ __('events.manage') }}</a>@endif
