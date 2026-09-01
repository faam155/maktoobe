<x-layouts.admin :title="__('prompts.edit')">
<form method="POST" action="{{ route('admin.prompts.update',$prompt) }}" class="admin-form">@csrf @method('PUT') @include('admin.prompts._form')</form>
<div class="prompt-admin-actions"><a class="admin-button" href="{{ route('admin.prompts.show',$prompt) }}">{{ __('prompts.preview') }}</a>
@can('publish',$prompt)<form method="POST" action="{{ route('admin.prompts.status',$prompt) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $prompt->status->value==='published'?'draft':'published' }}"><button class="admin-button">{{ $prompt->status->value==='published'?__('prompts.unpublish'):__('prompts.publish') }}</button></form>@endcan
<form method="POST" action="{{ route('admin.prompts.status',$prompt) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="archived"><button class="admin-text-button">{{ __('prompts.archive') }}</button></form>
<form method="POST" action="{{ route('admin.prompts.duplicate',$prompt) }}">@csrf<button class="admin-text-button">{{ __('prompts.duplicate') }}</button></form></div>
<section class="admin-danger-zone"><form method="POST" action="{{ route('admin.prompts.destroy',$prompt) }}" onsubmit="return confirm(@js(__('prompts.delete_confirm')))">@csrf @method('DELETE')<button class="admin-danger-button">{{ __('prompts.delete') }}</button></form></section>
</x-layouts.admin>
