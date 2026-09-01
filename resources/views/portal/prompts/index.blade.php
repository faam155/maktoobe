<x-layouts.portal :title="__('prompts.library')">
<section class="portal-welcome"><p class="portal-eyebrow">{{ __('prompts.library') }}</p><h2>{{ __('prompts.library') }}</h2><p>{{ __('prompts.library_intro') }}</p></section>
<form method="GET" class="prompt-library-filters">
<label>{{ __('prompts.search') }}<input name="search" value="{{ $filters['search']??'' }}" maxlength="100" placeholder="{{ __('prompts.search_placeholder') }}"></label>
<label>{{ __('prompts.category') }}<select name="category"><option value="">{{ __('prompts.all_categories') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)($filters['category']??'')===(string)$category->id)>{{ $category->localizedName() }}</option>@endforeach</select></label>
<label>{{ __('prompts.tags') }}<select name="tag"><option value="">{{ __('prompts.all_tags') }}</option>@foreach($tags as $tag)<option value="{{ $tag->canonical_name }}" @selected(($filters['tag']??'')===$tag->canonical_name)>{{ $tag->display_name }}</option>@endforeach</select></label>
<label>{{ __('prompts.sort') }}<select name="sort">@foreach(['newest','title','popular'] as $sort)<option value="{{ $sort }}" {{ ($filters['sort']??'newest')===$sort ? 'selected' : '' }}>{{ __('prompts.sorts.'.$sort) }}</option>@endforeach</select></label>
<div class="prompt-filter-actions"><button class="admin-button">{{ __('prompts.filter') }}</button><a href="{{ route('prompts.index') }}">{{ __('prompts.clear') }}</a></div></form>
<div class="prompt-card-grid">@forelse($prompts as $prompt)<x-prompt-card :prompt="$prompt" />@empty<div class="prompt-empty"><h2>{{ __('prompts.none_library') }}</h2><a href="{{ route('prompts.index') }}">{{ __('prompts.clear') }}</a></div>@endforelse</div>
<div class="admin-pagination">{{ $prompts->links() }}</div>
</x-layouts.portal>
