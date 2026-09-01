<x-layouts.admin :title="__('categories.title')">
<div class="admin-heading-row"><p class="admin-lead">{{ __('categories.intro') }}</p><a class="admin-button" href="{{ route('admin.prompt-categories.create') }}">{{ __('categories.create') }}</a></div>
<form method="GET" class="admin-filters category-filters">
<label>{{ __('categories.search') }}<input name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="{{ __('categories.search_placeholder') }}"></label>
<label>{{ __('categories.status') }}<select name="status"><option value="">{{ __('categories.all_statuses') }}</option><option value="active" @selected(($filters['status']??'')==='active')>{{ __('categories.active') }}</option><option value="inactive" @selected(($filters['status']??'')==='inactive')>{{ __('categories.inactive') }}</option></select></label>
<div class="admin-filter-actions"><button class="admin-button" type="submit">{{ __('categories.filter') }}</button><a href="{{ route('admin.prompt-categories.index') }}">{{ __('categories.clear') }}</a></div>
</form>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>{{ __('categories.category') }}</th><th>{{ __('categories.slug') }}</th><th>{{ __('categories.status') }}</th><th>{{ __('categories.order') }}</th><th>{{ __('categories.actions') }}</th></tr></thead><tbody>
@forelse($categories as $category)<tr>
<td data-label="{{ __('categories.category') }}"><strong>{{ $category->localizedName() }}</strong><small>{{ app()->getLocale()==='ar' ? $category->name_en : $category->name_ar }}</small></td>
<td data-label="{{ __('categories.slug') }}"><bdi>{{ $category->slug }}</bdi></td>
<td data-label="{{ __('categories.status') }}"><span class="admin-badge {{ $category->is_active ? 'status-active':'status-disabled' }}">{{ $category->is_active ? __('categories.active'):__('categories.inactive') }}</span></td>
<td data-label="{{ __('categories.order') }}"><div class="category-order"><span>{{ $category->display_order }}</span><form method="POST" action="{{ route('admin.prompt-categories.move',$category) }}">@csrf @method('PATCH')<input type="hidden" name="direction" value="up"><button type="submit" aria-label="{{ __('categories.move_up',['name'=>$category->localizedName()]) }}">↑</button></form><form method="POST" action="{{ route('admin.prompt-categories.move',$category) }}">@csrf @method('PATCH')<input type="hidden" name="direction" value="down"><button type="submit" aria-label="{{ __('categories.move_down',['name'=>$category->localizedName()]) }}">↓</button></form></div></td>
<td data-label="{{ __('categories.actions') }}"><div class="category-actions"><a href="{{ route('admin.prompt-categories.edit',$category) }}">{{ __('categories.edit') }}</a><form method="POST" action="{{ route('admin.prompt-categories.status',$category) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $category->is_active ? 0:1 }}"><button class="admin-text-button" type="submit">{{ $category->is_active ? __('categories.deactivate'):__('categories.activate') }}</button></form></div></td>
</tr>@empty<tr><td colspan="5" class="admin-empty">{{ __('categories.none') }}</td></tr>@endforelse
</tbody></table></div><div class="admin-pagination">{{ $categories->links() }}</div>
</x-layouts.admin>
