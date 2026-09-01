@props(['prompt'])
<article {{ $attributes->class('prompt-card') }}>
    <div class="prompt-card-meta">@if($prompt->category)<span>{{ $prompt->category->localizedName() }}</span>@endif<span>{{ __('prompts.uses') }}: {{ $prompt->uses_count ?? 0 }}</span></div>
    <h2><a href="{{ route('prompts.show',$prompt) }}">{{ $prompt->title }}</a></h2>
    @if($prompt->description)<p>{{ $prompt->description }}</p>@endif
    <div class="prompt-tags">@foreach($prompt->tags as $tag)<span>{{ $tag->display_name }}</span>@endforeach</div>
    <a class="prompt-card-link" href="{{ route('prompts.show',$prompt) }}">{{ __('prompts.preview') }}</a>
    @if($prompt->source === \App\Enums\PromptSource::Library)
    <form method="POST" action="{{ $prompt->is_favorite ? route('prompts.unfavorite',$prompt) : route('prompts.favorite',$prompt) }}">@csrf @if($prompt->is_favorite)@method('DELETE')@endif<button class="prompt-favorite-button">{{ $prompt->is_favorite ? __('prompts.remove_favorite') : __('prompts.add_favorite') }}</button></form>
    @endif
</article>
