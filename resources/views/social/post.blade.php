<x-layouts.portal :title="$post->title"><div class="social-workspace">@include('social._nav')
<a href="{{ route('social.dashboard',[$account]+$f) }}">{{ __('social.back') }}</a>
<section class="social-section"><h2 dir="auto">{{ $post->title }}</h2><p class="social-text" dir="auto">{{ $post->content }}</p>
@if($post->thumbnail_url)<img class="social-thumbnail" src="{{ $post->thumbnail_url }}" alt="" referrerpolicy="no-referrer">@endif
@if($post->url)<a href="{{ $post->url }}" target="_blank" rel="noopener noreferrer">{{ __('social.url') }}</a>@endif
<dl class="social-inline-metrics">@foreach(['impressions','reactions','comments','shares','clicks','engagements','engagement_rate'] as $key)<div><dt>{{ __('social.'.$key) }}</dt><dd>{{ $stats?->$key??'—' }}</dd></div>@endforeach</dl>
<p><bdi>{{ $post->published_at->setTimezone($account->timezone)->format('Y-m-d H:i') }}</bdi> · {{ $post->contentType->label() }} · {{ __('social.'.$post->language) }} · {{ $post->campaign?->name }} · {{ $post->event?->title }}</p>
<p>{{ $post->tags->pluck('name')->join(' · ') }}</p></section>
<section class="social-section"><h2>{{ __('social.comparison') }}</h2>@foreach($comparison as $key=>$value)<p>{{ __('social.'.$key) }}: {{ $value===null?'—':\App\Services\Social\SocialFormat::number($value,2).'%' }}</p>@endforeach</section>
@can('linkedin.posts.edit')<a class="social-button" href="{{ route('social.posts.edit',[$account,$post]) }}">{{ __('social.edit') }}</a>@endcan
@can('linkedin.posts.delete')<details><summary>{{ __('social.delete') }}</summary><form method="POST" action="{{ route('social.posts.delete',[$account,$post]) }}">@csrf @method('DELETE')<button class="social-button">{{ __('social.confirm_delete') }}</button></form></details>@endcan
</div></x-layouts.portal>
