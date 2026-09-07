<x-layouts.admin :title="__('reporting.analytics_title')">
    <p class="report-explanation">{{ __('reporting.scope') }}</p>
    <p class="report-explanation">{{ __('reporting.dates') }}</p>
    <form method="get" class="report-filters">
        @include('admin.reporting.period')
        <button class="admin-button">{{ __('reporting.apply') }}</button>
    </form>
    <div class="analytics-metrics">
        @foreach($metrics as $key=>$value)
            <article data-metric="{{ $key }}"><h2>{{ __('reporting.'.$key) }}</h2><strong>{{ number_format($value) }}</strong></article>
        @endforeach
    </div>
    <p class="report-explanation">{{ __('reporting.ranking_note') }}</p>
    <div class="analytics-rankings">
        @foreach(['top_prompts'=>$topPrompts,'categories'=>$categories] as $key=>$rows)
            <section><h2>{{ __('reporting.'.$key) }}</h2>
                @forelse($rows as $row)
                    <div class="analytics-rank"><span dir="auto">{{ $row['name'] }}</span><strong>{{ $row['count'] }} {{ __('reporting.uses') }}</strong><meter min="0" max="{{ max(array_column($rows,'count')) }}" value="{{ $row['count'] }}" aria-label="{{ $row['name'] }}">{{ $row['count'] }}</meter></div>
                @empty<p>{{ __('reporting.empty') }}</p>@endforelse
            </section>
        @endforeach
    </div>
</x-layouts.admin>
