<div class="page-content">
    <section id="overview" class="hero" aria-labelledby="hero-heading">
        <div class="hero-copy">
            <p class="eyebrow"><span aria-hidden="true"></span>{{ __('foundation.eyebrow') }}</p>
            <h1 id="hero-heading">{{ __('foundation.headline_start') }}<br><em>{{ __('foundation.headline_end') }}</em></h1>
            <p class="hero-description">{{ __('foundation.intro') }}</p>
            <a href="#foundation" class="button button-primary">{{ __('foundation.explore') }}<x-icon name="arrow" class="directional" /></a>
        </div>
        <div class="edition-panel" aria-hidden="true">
            <span class="edition-label">{{ __('foundation.edition') }}</span>
            <div class="paper-scene" dir="ltr">
                <div class="scene-orbit"></div><div class="scene-orbit orbit-two"></div>
                <div class="paper paper-back"></div>
                <div class="paper paper-front"><span class="paper-mark">m.</span><i></i><i></i><i></i><span class="paper-corner">01</span></div>
                <span class="scene-dot"></span><span class="scene-cross">+</span>
            </div>
            <span class="edition-caption">{{ __('foundation.edition_note') }}</span>
        </div>
    </section>

    <section id="foundation" class="foundation-section" aria-labelledby="foundation-heading">
        <div class="section-heading"><div><p class="eyebrow">{{ __('foundation.foundation_label') }}</p><h2 id="foundation-heading">{{ __('foundation.foundation_title') }}</h2></div><span class="availability"><span class="status-dot" aria-hidden="true"></span>{{ __('foundation.ready') }}</span></div>
        <p class="section-intro">{{ __('foundation.foundation_intro') }}</p>
        <div class="foundation-grid">
            <article class="foundation-card"><span class="feature-icon"><x-icon name="language" /></span><h3>{{ __('foundation.language_title') }}</h3><p>{{ __('foundation.language_body') }}</p><span class="card-index" aria-hidden="true">01</span></article>
            <article class="foundation-card"><span class="feature-icon"><x-icon name="screens" /></span><h3>{{ __('foundation.responsive_title') }}</h3><p>{{ __('foundation.responsive_body') }}</p><span class="card-index" aria-hidden="true">02</span></article>
            <article class="foundation-card"><span class="feature-icon"><x-icon name="layers" /></span><h3>{{ __('foundation.private_title') }}</h3><p>{{ __('foundation.private_body') }}</p><span class="card-index" aria-hidden="true">03</span></article>
        </div>
        <aside class="next-phase"><div class="next-icon"><x-icon name="lock" /></div><div><p class="eyebrow">{{ __('foundation.next_label') }}</p><h3>{{ __('foundation.next_title') }}</h3><p>{{ __('foundation.next_body') }}</p></div><a class="planned-label" href="{{ route('login') }}">{{ __('auth.login_title') }}</a></aside>
    </section>

    <section id="preferences" class="preferences-section" aria-labelledby="preferences-heading">
        <div class="preferences-copy"><p class="eyebrow">{{ __('foundation.preferences_label') }}</p><h2 id="preferences-heading">{{ __('foundation.preferences_title') }}</h2><p>{{ __('foundation.preferences_body') }}</p></div>
        <form wire:submit="saveLocale" class="preferences-form" aria-label="{{ __('foundation.preferences') }}">
            <label for="locale">{{ __('foundation.language') }}</label>
            <select id="locale" wire:model="locale" aria-describedby="locale-hint{{ $errors->has('locale') ? ' locale-error' : '' }}" @if($errors->has('locale')) aria-invalid="true" @endif>
                <option value="en" lang="en">{{ __('foundation.english') }}</option>
                <option value="ar" lang="ar">{{ __('foundation.arabic') }}</option>
            </select>
            <p id="locale-hint" class="field-hint">{{ __('foundation.language_hint') }}</p>
            @error('locale')<p id="locale-error" class="field-error" role="alert">{{ $message }}</p>@enderror
            <button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="saveLocale"><span wire:loading.remove wire:target="saveLocale">{{ __('foundation.save_language') }}</span><span wire:loading wire:target="saveLocale">{{ __('foundation.saving') }}</span><x-icon name="arrow" class="directional" /></button>
            @if(session('locale_updated'))<p class="saved-message" role="status"><x-icon name="check" />{{ __('foundation.language_saved') }}</p>@endif
        </form>
    </section>
</div>
