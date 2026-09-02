<x-dynamic-component :component="$administration ? 'layouts.admin' : 'layouts.portal'" :title="$event->title.' · '.__('event_files.title')">
@php($fileRoutePrefix = $administration ? 'admin.events.files' : 'events.files')
@include('portal.events._workspace-tabs')
@if(!$administration && $errors->any())<div role="alert" class="admin-errors">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@can('create', [\App\Models\EventFile::class, $event])
<section class="event-file-upload"><h2>{{ __('event_files.upload') }}</h2>
<p>{{ __('event_files.help') }}</p>
@if(app()->environment(['local','browser']))<p class="form-help">{{ __('event_files.local_scanner') }}</p>@endif
<form method="post" enctype="multipart/form-data" action="{{ route($fileRoutePrefix.'.store',$event) }}" data-event-upload data-failed="{{ __('event_files.failed') }}" data-processing="{{ __('event_files.processing') }}">
    @csrf
    <label>{{ __('event_files.choose') }}<input type="file" name="files[]" multiple required accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx,.txt"></label>
    <label>{{ __('event_files.category') }}<select name="category">@foreach(\App\Enums\EventFileCategory::cases() as $category)<option value="{{ $category->value }}" @selected(old('category',$filters['category']??'other')===$category->value)>{{ __('event_files.'.$category->value) }}</option>@endforeach</select></label>
    <label class="event-file-wide">{{ __('event_files.caption') }}<textarea name="caption" maxlength="500" rows="2">{{ old('caption') }}</textarea></label>
    <div><button class="admin-button">{{ __('event_files.upload') }}</button></div>
    <div class="event-file-wide" data-upload-feedback hidden><label>{{ __('event_files.progress') }}<progress max="100" value="0"></progress></label><p role="status" aria-live="polite"></p></div>
</form></section>
@endcan
<form method="get" class="event-file-filter"><label>{{ __('event_files.category') }}<select name="category"><option value="">{{ __('event_files.all') }}</option>@foreach(\App\Enums\EventFileCategory::cases() as $category)<option value="{{ $category->value }}" @selected(($filters['category']??'')===$category->value)>{{ __('event_files.'.$category->value) }}</option>@endforeach</select></label><button class="admin-button admin-button-secondary">{{ __('event_files.filter') }}</button></form>
<section class="event-file-gallery" aria-label="{{ __('event_files.title') }}">
@forelse($files as $file)
<article class="event-file-card">
    @if($file->isImage())<a class="event-file-preview" href="{{ route($fileRoutePrefix.'.preview',[$event,$file]) }}" target="_blank" rel="noopener"><img src="{{ route($fileRoutePrefix.'.preview',[$event,$file]) }}" alt="{{ $file->caption ?: $file->original_name }}" loading="lazy"><span>{{ __('event_files.preview') }}</span></a>@else<div class="event-file-type"><bdi>{{ strtoupper($file->extension) }}</bdi></div>@endif
    <div class="event-file-details"><h3><bdi>{{ $file->original_name }}</bdi></h3><p>{{ $file->caption }}</p><small>{{ __('event_files.'.$file->category->value) }} · <bdi>{{ number_format($file->file_size/1024,1) }} KiB</bdi></small><small>{{ __('event_files.by',['name'=>$file->uploader?->name ?? __('event_files.unknown')]) }}</small><time datetime="{{ $file->created_at->toIso8601String() }}">{{ $file->created_at->setTimezone(auth()->user()->timezone ?: 'UTC')->translatedFormat('j M Y H:i') }}</time>
    <a class="admin-button admin-button-secondary" href="{{ route($fileRoutePrefix.'.download',[$event,$file]) }}">{{ __('event_files.download') }}</a>
    @can('update',$file)<details><summary>{{ __('event_files.edit') }}</summary><form method="post" action="{{ route($fileRoutePrefix.'.update',[$event,$file]) }}">@csrf @method('PATCH')<label>{{ __('event_files.caption') }}<textarea name="caption" maxlength="500">{{ $file->caption }}</textarea></label><label>{{ __('event_files.category') }}<select name="category">@foreach(\App\Enums\EventFileCategory::cases() as $category)<option value="{{ $category->value }}" @selected($file->category===$category)>{{ __('event_files.'.$category->value) }}</option>@endforeach</select></label><label>{{ __('event_files.order') }}<input type="number" name="display_order" min="0" max="1000000" value="{{ $file->display_order }}" required></label><button class="admin-button">{{ __('event_files.save') }}</button></form></details>@endcan
    @can('delete',$file)<details><summary>{{ __('event_files.delete') }}</summary><form method="post" action="{{ route($fileRoutePrefix.'.destroy',[$event,$file]) }}">@csrf @method('DELETE')<p>{{ __('event_files.retention') }}</p><label class="event-file-confirm"><input type="checkbox" name="confirm" value="1" required>{{ __('event_files.confirm') }}</label><button class="prompt-danger-button">{{ __('event_files.delete') }}</button></form></details>@endcan
    </div>
</article>
@empty<p class="event-file-empty">{{ __('event_files.empty') }}</p>@endforelse
</section>
{{ $files->links() }}
</x-dynamic-component>
