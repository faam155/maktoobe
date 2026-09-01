<x-layouts.portal :title="__('ai.title')">
<section class="portal-welcome"><p class="portal-eyebrow">{{ __('ai.title') }}</p><h2>{{ __('ai.title') }}</h2><p>{{ __('ai.intro') }}</p><a class="admin-button" href="{{ route('ai.create') }}">{{ __('ai.new') }}</a></section>
<div class="ai-conversation-list">@forelse($conversations as $conversation)<a href="{{ route('ai.show',$conversation) }}"><strong>{{ $conversation->title }}</strong><span dir="ltr">{{ $conversation->model }}</span><time>{{ $conversation->updated_at->locale(app()->getLocale())->diffForHumans() }}</time></a>@empty<div class="prompt-empty"><h2>{{ __('ai.empty') }}</h2><p>{{ __('ai.empty_help') }}</p></div>@endforelse</div>
<div class="admin-pagination">{{ $conversations->links() }}</div>
</x-layouts.portal>
