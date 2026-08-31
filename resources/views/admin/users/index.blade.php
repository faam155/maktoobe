<x-layouts.admin :title="__('admin.users')">
<div class="admin-heading-row"><p class="admin-lead">{{ __('admin.users_intro') }}</p>@can('create',\App\Models\User::class)<a class="admin-button" href="{{ route('admin.users.create') }}">{{ __('admin.create_user') }}</a>@endcan</div>
<form method="GET" class="admin-filters">
<label>{{ __('admin.search') }}<input name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="{{ __('admin.search_placeholder') }}"></label>
<label>{{ __('admin.status') }}<select name="status"><option value="">{{ __('admin.all_statuses') }}</option>@foreach(['pending','active','disabled'] as $status)<option value="{{ $status }}" @selected(($filters['status']??'')===$status)>{{ __('admin.statuses.'.$status) }}</option>@endforeach</select></label>
<label>{{ __('admin.role') }}<select name="role"><option value="">{{ __('admin.all_roles') }}</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected((string)($filters['role']??'')===(string)$role->id)>{{ __('admin.role_names.'.$role->name,[],app()->getLocale()) !== 'admin.role_names.'.$role->name ? __('admin.role_names.'.$role->name) : $role->name }}</option>@endforeach</select></label>
<div class="admin-filter-actions"><button class="admin-button" type="submit">{{ __('admin.filter') }}</button><a href="{{ route('admin.users.index') }}">{{ __('admin.clear') }}</a></div>
</form>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>{{ __('admin.name') }}</th><th>{{ __('admin.status') }}</th><th>{{ __('admin.roles') }}</th><th>{{ __('admin.created') }}</th><th>{{ __('admin.actions') }}</th></tr></thead><tbody>
@forelse($users as $user)<tr><td data-label="{{ __('admin.name') }}"><strong>{{ $user->name }}</strong><small><bdi>{{ $user->email }}</bdi> · <bdi>{{ $user->username }}</bdi></small></td><td data-label="{{ __('admin.status') }}"><span class="admin-badge status-{{ $user->status->value }}">{{ __('admin.statuses.'.$user->status->value) }}</span></td><td data-label="{{ __('admin.roles') }}">{{ $user->roles->pluck('name')->map(fn($name)=>__('admin.role_names.'.$name) !== 'admin.role_names.'.$name ? __('admin.role_names.'.$name) : $name)->join('، ') ?: __('admin.no_roles_assigned') }}</td><td data-label="{{ __('admin.created') }}">{{ $user->created_at->locale(app()->getLocale())->translatedFormat('Y M j') }}</td><td data-label="{{ __('admin.actions') }}"><a href="{{ route('admin.users.show',$user) }}">{{ __('admin.view') }}</a></td></tr>
@empty<tr><td colspan="5" class="admin-empty">{{ __('admin.no_users') }}</td></tr>@endforelse
</tbody></table></div>
<div class="admin-pagination">{{ $users->links() }}</div>
</x-layouts.admin>
