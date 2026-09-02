<x-layouts.portal :title="__('dashboard.title')">
<section class="portal-welcome">
    <p class="portal-eyebrow">{{ __('dashboard.dashboard') }}</p>
    <h2>{{ __('dashboard.welcome',['name'=>auth()->user()->name]) }}</h2>
    <p>{{ __('dashboard.intro') }}</p>
</section>

<section class="portal-section" aria-labelledby="quick-access-title">
    <div class="portal-section-heading"><div><h2 id="quick-access-title">{{ __('dashboard.quick_access') }}</h2><p>{{ __('dashboard.quick_access_intro') }}</p></div></div>
    <div class="portal-quick-grid">
        @foreach($quickActions as $action)
            <div class="portal-quick-card" @if(!$action['route']) aria-disabled="true" @endif>
                <span class="portal-card-mark" aria-hidden="true">0{{ $loop->iteration }}</span>
                <h3>{{ __('dashboard.'.$action['key']) }}</h3>
                @if($action['route'])<p>{{ $action['key']==='ai_assistant' ? __('ai.intro') : ($action['key']==='events' ? __('events.portal_intro') : __('prompts.library_intro')) }}</p><a href="{{ route($action['route']) }}">{{ $action['key']==='ai_assistant' ? __('ai.new') : ($action['key']==='events' ? __('events.view') : __('prompts.browse_library')) }}</a>@else<p>{{ __('dashboard.unavailable_description') }}</p><span class="portal-unavailable">{{ __('dashboard.unavailable') }}</span>@endif
            </div>
        @endforeach
    </div>
</section>

<section class="portal-section" aria-labelledby="workspace-summary-title">
    <div class="portal-section-heading"><div><h2 id="workspace-summary-title">{{ __('dashboard.workspace_summary') }}</h2><p>{{ __('dashboard.workspace_summary_intro') }}</p></div></div>
    <div class="portal-summary-grid">
        @foreach($sections as $section)
            <article class="portal-summary-card">
                <div><h3>{{ __('dashboard.'.$section['key']) }}</h3><span>{{ array_key_exists('count',$section) ? $section['count'] : __('dashboard.unavailable') }}</span></div>
                @if(isset($section['route']))<p><a href="{{ route($section['route'],$section['params']??[]) }}">{{ str_contains($section['key'],'events') ? __('events.view') : __('prompts.my_prompts') }}</a></p>@else<p>{{ __('dashboard.unavailable_description') }}</p>@endif
            </article>
        @endforeach
    </div>
</section>
</x-layouts.portal>
