<x-layouts.admin :title="__('admin.role_details')">
<div class="admin-heading-row"><div><h2>{{ __('admin.role_names.'.$role->name) !== 'admin.role_names.'.$role->name ? __('admin.role_names.'.$role->name) : $role->name }}</h2><p class="admin-lead">{{ $role->permissions->count() }} {{ __('admin.permissions_title') }}</p></div>
@can('update',$role)
<a class="admin-button admin-button-secondary" href="{{ route('admin.roles.edit',$role) }}">{{ __('admin.edit_role') }}</a>
@else
@if(\App\Support\Authorization\Access::isProtectedRole($role->name))<span class="admin-badge">{{ __('admin.protected_role') }}</span>@endif
@endcan
</div>
<div class="admin-permission-list">@forelse($role->permissions->sortBy('name') as $permission)<span>{{ __('admin.permissions.'.$permission->name) }}</span>@empty<span>{{ __('admin.no_permissions') }}</span>@endforelse</div>
<section class="admin-section"><h2>{{ __('admin.users_in_role') }}</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>{{ __('admin.name') }}</th><th>{{ __('admin.status') }}</th><th>{{ __('admin.actions') }}</th></tr></thead><tbody>@forelse($users as $user)<tr><td data-label="{{ __('admin.name') }}"><strong>{{ $user->name }}</strong><small><bdi>{{ $user->email }}</bdi></small></td><td data-label="{{ __('admin.status') }}">{{ __('admin.statuses.'.$user->status->value) }}</td><td data-label="{{ __('admin.actions') }}"><a href="{{ route('admin.users.show',$user) }}">{{ __('admin.view') }}</a></td></tr>@empty<tr><td colspan="3" class="admin-empty">{{ __('admin.no_users') }}</td></tr>@endforelse</tbody></table></div><div class="admin-pagination">{{ $users->links() }}</div></section>
</x-layouts.admin>
