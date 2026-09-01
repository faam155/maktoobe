<a href="{{ route('account.home') }}" @class(['portal-nav-link','is-current'=>request()->routeIs('account.home')])>{{ __('dashboard.dashboard') }}</a>
@can('use-ai')<span class="portal-nav-link is-disabled" aria-disabled="true"><span>{{ __('dashboard.ai_assistant') }}</span><small>{{ __('dashboard.coming_later') }}</small></span>@endcan
<a href="{{ route('prompts.index') }}" @class(['portal-nav-link','is-current'=>request()->routeIs('prompts.*')])>{{ __('dashboard.prompt_library') }}</a>
<a href="{{ route('my-prompts.index') }}" @class(['portal-nav-link','is-current'=>request()->routeIs('my-prompts.*')])>{{ __('dashboard.my_prompts') }}</a>
<span class="portal-nav-link is-disabled" aria-disabled="true"><span>{{ __('dashboard.events') }}</span><small>{{ __('dashboard.coming_later') }}</small></span>
<span class="portal-nav-link is-disabled" aria-disabled="true"><span>{{ __('dashboard.notifications') }}</span><small>{{ __('dashboard.coming_later') }}</small></span>
<a href="{{ route('account.security') }}" @class(['portal-nav-link','is-current'=>request()->routeIs('account.*') && !request()->routeIs('account.home')])>{{ __('dashboard.profile') }}</a>
@can('access-admin')<a href="{{ route('admin.dashboard') }}" class="portal-nav-link">{{ __('dashboard.administration') }}</a>@endcan
