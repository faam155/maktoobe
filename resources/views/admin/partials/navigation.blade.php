<a href="{{ route('admin.dashboard') }}" @class(['admin-nav-link','is-current'=>request()->routeIs('admin.dashboard')])>{{ __('admin.dashboard') }}</a>
@can('manage-users')<a href="{{ route('admin.users.index') }}" @class(['admin-nav-link','is-current'=>request()->routeIs('admin.users.*')])>{{ __('admin.users') }}</a>@endcan
@if(auth()->user()->can('manage-roles'))<a href="{{ route('admin.roles.index') }}" @class(['admin-nav-link','is-current'=>request()->routeIs('admin.roles.*') || request()->routeIs('admin.permissions.*')])>{{ __('admin.roles_permissions') }}</a>@elseif(auth()->user()->can('manage-permissions'))<a href="{{ route('admin.permissions.index') }}" @class(['admin-nav-link','is-current'=>request()->routeIs('admin.permissions.*')])>{{ __('admin.roles_permissions') }}</a>@endif
@can('manage-prompts')<span class="admin-nav-link is-disabled" aria-disabled="true"><span>{{ __('admin.prompts') }}</span><small>{{ __('admin.coming_later') }}</small></span>@endcan
@can('manage-categories')<a href="{{ route('admin.prompt-categories.index') }}" class="admin-nav-link">{{ __('admin.prompt_categories') }}</a>@endcan
@can('manage-events')<span class="admin-nav-link is-disabled" aria-disabled="true"><span>{{ __('admin.events') }}</span><small>{{ __('admin.coming_later') }}</small></span>@endcan
@can('manage-brand-guidelines')<span class="admin-nav-link is-disabled" aria-disabled="true"><span>{{ __('admin.brand_guidelines') }}</span><small>{{ __('admin.coming_later') }}</small></span>@endcan
@can('manage-ai-settings')<span class="admin-nav-link is-disabled" aria-disabled="true"><span>{{ __('admin.ai_settings') }}</span><small>{{ __('admin.coming_later') }}</small></span>@endcan
@can('view-analytics')<span class="admin-nav-link is-disabled" aria-disabled="true"><span>{{ __('admin.analytics') }}</span><small>{{ __('admin.coming_later') }}</small></span>@endcan
@can('manage-system-settings')<span class="admin-nav-link is-disabled" aria-disabled="true"><span>{{ __('admin.system_settings') }}</span><small>{{ __('admin.coming_later') }}</small></span>@endcan
<a href="{{ route('account.home') }}" class="admin-nav-link">{{ __('admin.back_workspace') }}</a>
