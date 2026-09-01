<x-layouts.admin :title="__('categories.edit')">
<form method="POST" action="{{ route('admin.prompt-categories.update',$category) }}" class="admin-form">@csrf @method('PUT') @include('admin.prompt-categories._form')</form>
<section class="admin-danger-zone"><h2>{{ __('categories.delete_title') }}</h2><p>{{ __('categories.delete_help') }}</p><form method="POST" action="{{ route('admin.prompt-categories.destroy',$category) }}" onsubmit="return confirm(@js(__('categories.delete_confirm')))" >@csrf @method('DELETE')<button class="admin-danger-button" type="submit">{{ __('categories.delete') }}</button></form></section>
</x-layouts.admin>
