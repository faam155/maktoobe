<x-layouts.admin :title="__('admin.dashboard')">
<p class="admin-lead">{{ __('admin.dashboard_intro') }}</p>
<div class="admin-cards">
@if($userCount !== null)<a class="admin-card" href="{{ route('admin.users.index') }}"><span>{{ __('admin.total_users') }}</span><strong>{{ $userCount }}</strong><small>{{ __('admin.manage_users') }}</small></a>@endif
@if($roleCount !== null)<a class="admin-card" href="{{ route('admin.roles.index') }}"><span>{{ __('admin.total_roles') }}</span><strong>{{ $roleCount }}</strong><small>{{ __('admin.manage_roles') }}</small></a>@endif
@can('manage-permissions')<a class="admin-card" href="{{ route('admin.permissions.index') }}"><span>{{ __('admin.permissions_title') }}</span><strong>{{ \Spatie\Permission\Models\Permission::count() }}</strong><small>{{ __('admin.view_permissions') }}</small></a>@endcan
</div>
</x-layouts.admin>
