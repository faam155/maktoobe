<x-dynamic-component :component="$administration ? 'layouts.admin' : 'layouts.portal'" :title="$event->title.' · '.__('event_reports.title')">
@php
    $reportRoute = $administration ? 'admin.events.reports' : 'events.reports';
@endphp
@include('portal.events._workspace-tabs')
<p class="report-intro">{{ __('event_reports.intro') }}</p>
@if(!$administration && $errors->any())<div role="alert" class="admin-errors">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<nav class="report-jumps" aria-label="{{ __('event_reports.title') }}">@foreach($sections as $section)<a href="#{{ $section['type']->value }}">{{ __('event_reports.'.$section['type']->value) }}</a>@endforeach</nav>
@foreach($sections as $section)
@php
    $type = $section['type']->value;
    $report = $section['report'];
    $current = $report?->currentVersion;
    $canUpload = $report ? auth()->user()->can('update',$report) : auth()->user()->can('create',[\App\Models\EventReport::class,$event]);
@endphp
<section id="{{ $type }}" class="event-report-section" aria-labelledby="heading-{{ $type }}">
    <h2 id="heading-{{ $type }}">{{ __('event_reports.'.$type) }}</h2>
    @if($current)<div class="report-current"><div><span class="event-badge">{{ __('event_reports.current') }} · {{ __('event_reports.version',['number'=>$current->version_number]) }}</span><h3>{{ $current->title }}</h3></div><a class="admin-button" href="{{ route($reportRoute.'.download',[$event,$report,$current]) }}">{{ __('event_reports.download') }}</a></div>@else<p class="report-empty">{{ __('event_reports.empty') }}</p>@endif
    @if($canUpload)
    <details class="report-upload" @if(!$current) open @endif><summary>{{ __($current ? 'event_reports.replace' : 'event_reports.upload') }}</summary>
    <p>{{ __('event_reports.help') }}</p>
    @if(app()->environment(['local','browser']))<p class="form-help">{{ __('event_files.local_scanner') }}</p>@endif
    <form method="post" enctype="multipart/form-data" action="{{ route($reportRoute.'.store',$event) }}" data-event-upload data-failed="{{ __('event_files.failed') }}" data-processing="{{ __('event_files.processing') }}">
        @csrf<input type="hidden" name="type" value="{{ $type }}">
        <label>{{ __('event_reports.name') }}<input name="title" maxlength="180" required value="{{ old('type')===$type ? old('title') : $current?->title }}"></label>
        <label>{{ __('event_reports.file') }}<input type="file" name="file" required accept=".pdf,.docx,.xlsx"></label>
        <label class="event-file-wide">{{ __('event_reports.notes') }}<textarea name="notes" maxlength="5000" rows="3">{{ old('type')===$type ? old('notes') : '' }}</textarea></label>
        <button class="admin-button">{{ __($current ? 'event_reports.replace' : 'event_reports.upload') }}</button>
        <div class="event-file-wide" data-upload-feedback hidden><label>{{ __('event_files.progress') }}<progress max="100" value="0"></progress></label><p role="status" aria-live="polite"></p></div>
    </form></details>
    @endif
    @if($report)
    <h3 class="report-history-heading">{{ __('event_reports.history') }}</h3>
    <div class="report-history">@foreach($section['versions'] as $version)<article class="report-version">
        <div><span class="event-badge">{{ __('event_reports.version',['number'=>$version->version_number]) }} · {{ __($version->id===$current?->id ? 'event_reports.current' : 'event_reports.previous') }}</span><h4>{{ $version->title }}</h4><p>{{ $version->notes }}</p></div>
        <dl><div><dt>{{ __('event_reports.uploaded_at') }}</dt><dd><time datetime="{{ $version->created_at->toIso8601String() }}">{{ $version->created_at->setTimezone(auth()->user()->timezone ?: 'UTC')->translatedFormat('j M Y H:i') }}</time></dd></div><div><dt>{{ __('event_reports.uploader') }}</dt><dd>{{ $version->file?->uploader?->name ?? __('event_reports.unknown') }}</dd></div></dl>
        @if($version->file)<p><bdi>{{ $version->file->original_name }}</bdi> · <bdi>{{ number_format($version->file->file_size/1024,1) }} KiB</bdi></p>@endif
        <a href="{{ route($reportRoute.'.download',[$event,$report,$version]) }}">{{ __('event_reports.download') }}</a>
    </article>@endforeach</div>
    {{ $section['versions']->fragment($type)->links() }}
    @can('delete',$report)<details class="report-delete"><summary>{{ __('event_reports.delete') }}</summary><form method="post" action="{{ route($reportRoute.'.destroy',[$event,$report]) }}">@csrf @method('DELETE')<p>{{ __('event_reports.retention') }}</p><label><input type="checkbox" name="confirm" value="1" required>{{ __('event_reports.confirm') }}</label><button class="prompt-danger-button">{{ __('event_reports.delete') }}</button></form></details>@endcan
    @endif
</section>
@endforeach
</x-dynamic-component>
