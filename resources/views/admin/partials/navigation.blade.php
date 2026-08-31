<a href="{{ route('admin.dashboard') }}" @class(['admin-nav-link','is-current'=>request()->routeIs('admin.dashboard')])>{{ __('admin.dashboard') }}</a>
@can('manage-users')<a href="{{ route('admin.users.index') }}" @class(['admin-nav-link','is-current'=>request()->routeIs('admin.users.*')])>{{ __('admin.users') }}</a>@endcan
@can('manage-roles')<a href="{{ route('admin.roles.index') }}" @class(['admin-nav-link','is-current'=>request()->routeIs('admin.roles.*')])>{{ __('admin.roles') }}</a>@endcan
@can('manage-permissions')<a href="{{ route('admin.permissions.index') }}" @class(['admin-nav-link','is-current'=>request()->routeIs('admin.permissions.*')])>{{ __('admin.permissions_title') }}</a>@endcan
<a href="{{ route('account.home') }}" class="admin-nav-link">{{ __('admin.back_workspace') }}</a>
