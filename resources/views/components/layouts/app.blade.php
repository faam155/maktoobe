<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Enums\Locale::resolve(app()->getLocale())->direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('foundation.description') }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('foundation.page_title') }} · {{ __('foundation.brand') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <a class="skip-link" href="#main-content">{{ __('foundation.skip') }}</a>
    <div class="workspace-shell">
        <aside class="desktop-sidebar">
            <x-brand />
            <p class="sidebar-label">{{ __('foundation.workspace') }}</p>
            <x-navigation />
            <div class="sidebar-bottom">
                <span class="status-dot" aria-hidden="true"></span>
                <span>{{ __('foundation.preview') }}</span>
            </div>
        </aside>
        <div class="workspace-main">
            <header class="topbar" x-data>
                <div class="mobile-brand"><x-brand /></div>
                <p class="breadcrumb"><span>{{ __('foundation.workspace') }}</span><span aria-hidden="true">/</span>{{ __('foundation.overview') }}</p>
                <div class="topbar-actions">
                    <a class="language-link" href="#preferences" aria-label="{{ __('foundation.language') }}"><x-icon name="language" /><span>{{ __('foundation.language') }}</span></a>
                    <span class="phase-label">{{ __('foundation.phase') }}</span>
                    <button type="button" class="menu-button" @click="$refs.navigation.showModal()" aria-haspopup="dialog" aria-controls="mobile-navigation" aria-label="{{ __('foundation.open_menu') }}">
                        <x-icon name="menu" />
                    </button>
                </div>
                <dialog id="mobile-navigation" class="mobile-dialog" x-ref="navigation" @click.self="$el.close()" aria-label="{{ __('foundation.navigation') }}">
                    <div class="drawer-header"><x-brand /><button type="button" class="menu-button" @click="$refs.navigation.close()" aria-label="{{ __('foundation.close_menu') }}" autofocus><x-icon name="close" /></button></div>
                    <div @click="if ($event.target.closest('a')) $refs.navigation.close()"><x-navigation /></div>
                    <p class="drawer-note">{{ __('foundation.preview') }}</p>
                </dialog>
            </header>
            <noscript><div class="no-script"><x-navigation /><p>{{ __('foundation.no_javascript') }}</p></div></noscript>
            <main id="main-content" tabindex="-1">{{ $slot }}</main>
            <footer class="page-footer"><span>{{ __('foundation.footer_note') }}</span><span>{{ __('foundation.footer_status') }}</span></footer>
        </div>
    </div>
    @livewireScripts
</body>
</html>
