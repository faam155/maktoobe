<x-layouts.admin :title="__('admin.dashboard')">
<p class="admin-lead">{{ __('admin.dashboard_intro') }}</p>
<div class="admin-cards admin-dashboard-cards">
@foreach($userMetrics as $metric)<a class="admin-card" href="{{ route('admin.users.index',array_filter(['status'=>$metric['status']])) }}"><span>{{ __('admin.'.$metric['key']) }}</span><strong>{{ $metric['value'] }}</strong><small>{{ __('admin.manage_users') }}</small></a>@endforeach
@foreach($promptMetrics as $metric)<a class="admin-card" href="{{ route('admin.prompts.index') }}"><span>{{ __('admin.'.$metric['key']) }}</span><strong>{{ $metric['value'] }}</strong><small>{{ __('admin.prompts') }}</small></a>@endforeach
@foreach($aiMetrics as $metric)<article class="admin-card"><span>{{ __('admin.'.$metric['key']) }}</span><strong>{{ $metric['value'] }}</strong><small>{{ __('dashboard.ai_assistant') }}</small></article>@endforeach
@foreach($eventMetrics as $metric)<a class="admin-card" href="{{ route(auth()->user()->can('manage-events') ? 'admin.events.index' : 'events.index') }}"><span>{{ __('admin.'.$metric['key']) }}</span><strong>{{ $metric['value'] }}</strong><small>{{ __('admin.events') }}</small></a>@endforeach
@foreach($unavailableMetrics as $metric)<article class="admin-card is-unavailable" aria-disabled="true"><span>{{ __('admin.'.$metric['key']) }}</span><strong>{{ __('admin.metric_unavailable') }}</strong><small>{{ __('admin.unavailable_metric_help') }}</small></article>@endforeach
</div>

@if($recentActivity !== null)
<section class="admin-activity" aria-labelledby="recent-activity-title">
    <div><h2 id="recent-activity-title">{{ __('admin.recent_activity') }}</h2><p>{{ __('admin.recent_activity_intro') }}</p></div>
    @if($recentActivity->isEmpty())<p class="admin-activity-empty">{{ __('admin.no_recent_activity') }}</p>@else
    <ol>@foreach($recentActivity as $activity)
        @php($activityKey = 'admin.activities.'.str_replace('.', '_', $activity['action']))
        <li><div><strong>{{ \Illuminate\Support\Facades\Lang::has($activityKey) ? __($activityKey) : __('admin.activity_recorded') }}</strong><span>{{ __('admin.activity_subject',['name'=>$activity['subject']]) }}</span><span>{{ $activity['actor'] ? __('admin.activity_actor',['name'=>$activity['actor']]) : __('admin.activity_system') }}</span></div><time datetime="{{ $activity['created_at']->toIso8601String() }}">{{ $activity['created_at']->translatedFormat('M j, Y · H:i') }}</time></li>
    @endforeach</ol>
    @endif
</section>
@endif
</x-layouts.admin>
