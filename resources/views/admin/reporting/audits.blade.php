<x-layouts.admin :title="__('reporting.audit_title')">
    <p class="report-explanation">{{ __('reporting.audit_intro') }}</p>
    <form method="get" class="report-filters">
        @include('admin.reporting.period')
        <div class="audit-filter-fields">
            <label>{{ __('reporting.search') }}<input name="search" maxlength="80" value="{{ request('search') }}"></label>
            <label>{{ __('reporting.actor') }}<input type="number" min="1" name="actor_id" value="{{ request('actor_id') }}"></label>
            <label>{{ __('reporting.action') }}<select name="action"><option value="">{{ __('reporting.all') }}</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(request('action')===$action)>{{ $action }}</option>@endforeach</select></label>
            <label>{{ __('reporting.resource_type') }}<select name="resource_type"><option value="">{{ __('reporting.all') }}</option>@foreach($types as $type)<option value="{{ $type }}" @selected(request('resource_type')===$type)>{{ __('reporting.types.'.$type) }}</option>@endforeach</select></label>
            <label>{{ __('reporting.resource_id') }}<input type="number" min="1" name="resource_id" value="{{ request('resource_id') }}"></label>
        </div>
        <button class="admin-button">{{ __('reporting.apply') }}</button>
    </form>
    <div class="audit-list">
        @forelse($logs as $log)
            <article class="audit-entry">
                <div class="audit-entry-heading"><h2><bdi>{{ $log->action }}</bdi></h2><time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->setTimezone(auth()->user()->timezone ?: 'UTC')->translatedFormat('j M Y H:i') }}</time></div>
                <dl>
                    <div><dt>{{ __('reporting.actor_name') }}</dt><dd>{{ $log->actor?->name ?? __('reporting.system_actor') }} @if($log->actor_id)<bdi>#{{ $log->actor_id }}</bdi>@endif</dd></div>
                    <div><dt>{{ __('reporting.resource') }}</dt><dd>{{ __('reporting.types.'.$log->resource_type) }} <bdi>#{{ $log->resource_id ?? '—' }}</bdi></dd></div>
                </dl>
                <details><summary>{{ __('reporting.metadata') }}</summary><pre dir="ltr">{{ json_encode($log->metadata, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></details>
            </article>
        @empty<p>{{ __('reporting.empty') }}</p>@endforelse
    </div>
    {{ $logs->links() }}
</x-layouts.admin>
