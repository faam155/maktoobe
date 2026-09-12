<x-layouts.portal :title="__('social.sync')"><div class="social-workspace">@include('social._nav')
<p class="social-note">{{ __('social.sync_unavailable') }}</p>
@foreach($logs as $log)<section class="social-section"><h2>#{{ $log->id }} · {{ __('social.'.$log->status) }}</h2><p><bdi>{{ $log->started_at }}</bdi> — <bdi>{{ $log->completed_at??'—' }}</bdi></p><p>{{ __('social.processed') }}: {{ $log->processed }} · {{ __('social.created') }}: {{ $log->created }} · {{ __('social.updated') }}: {{ $log->updated }}</p>@if($log->error_code)<p>{{ __('social.'.$log->error_code) }}</p>@endif</section>@endforeach{{ $logs->links() }}
</div></x-layouts.portal>
