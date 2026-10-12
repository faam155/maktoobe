<div class="social-post-list">
@forelse($posts as $post)
<article class="social-post"><div>
@if($post->thumbnail_url)<img src="{{ $post->thumbnail_url }}" alt="{{ $post->title }}" loading="lazy" decoding="async" referrerpolicy="no-referrer" width="72" height="72">@endif
<h3>@can('linkedin.posts.view')<a href="{{ route('social.posts.show',[$account,$post]+$f) }}">{{ $post->title }}</a>@else{{ $post->title }}@endcan</h3>
<p dir="auto">{{ \Illuminate\Support\Str::limit($post->content,130) }}</p>
<p class="social-muted"><bdi>{{ $post->published_at->setTimezone($account->timezone)->format('Y-m-d H:i') }}</bdi> · {{ $post->contentType?->label() }} · {{ __('social.'.$post->language) }}</p>
<p>{{ $post->campaign?->name }} @if($post->event) · {{ $post->event->title }} @endif</p>
</div><dl class="social-inline-metrics">@foreach(['impressions','reactions','comments','shares','clicks','engagements','engagement_rate'] as $metric)<div><dt>{{ __('social.'.$metric) }}</dt><dd>{{ $post->$metric===null?'—':\App\Services\Social\SocialFormat::number($post->$metric,$metric==='engagement_rate'?2:0) }}{{ $metric==='engagement_rate'&&$post->$metric!==null?'%':'' }}</dd></div>@endforeach</dl></article>
@empty<p class="social-empty">{{ __('social.empty') }}</p>@endforelse</div>
