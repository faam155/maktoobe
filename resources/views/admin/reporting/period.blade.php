<div class="report-date-fields">
    <label>{{ __('reporting.period') }}<select name="period">@foreach(['today','7days','30days','month','custom'] as $key)<option value="{{ $key }}" @selected($period->filters['period']===$key)>{{ __('reporting.'.$key) }}</option>@endforeach</select></label>
    <label>{{ __('reporting.from') }}<input name="from" type="date" value="{{ $period->filters['from'] }}"></label>
    <label>{{ __('reporting.to') }}<input name="to" type="date" value="{{ $period->filters['to'] }}"></label>
</div>
