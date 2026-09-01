@props(['title'])
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale()==='ar' ? 'rtl':'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} · {{ __('foundation.brand') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="portal-body">
<a class="skip-link" href="#portal-main">{{ __('foundation.skip') }}</a>
<header class="portal-mobile-header"><x-brand /><details class="portal-menu"><summary>{{ __('dashboard.menu') }}</summary><nav aria-label="{{ __('dashboard.navigation') }}">@include('portal.partials.navigation')</nav></details></header>
<div class="portal-shell">
    <aside class="portal-sidebar">
        <x-brand />
        <nav aria-label="{{ __('dashboard.navigation') }}">@include('portal.partials.navigation')</nav>
        <p>{{ __('dashboard.signed_in_as',['name'=>auth()->user()->name]) }}</p>
    </aside>
    <div class="portal-workspace">
        <header class="portal-topbar">
            <div><p class="portal-kicker">{{ __('foundation.brand') }}</p><h1>{{ $title }}</h1></div>
            <div class="portal-top-actions">
                <form method="POST" action="{{ route('locale.update') }}" class="portal-locale">@csrf<label class="sr-only" for="portal-locale">{{ __('dashboard.language') }}</label><select id="portal-locale" name="locale"><option value="en" @selected(app()->getLocale()==='en')>English</option><option value="ar" @selected(app()->getLocale()==='ar')>العربية</option></select><button type="submit">{{ __('dashboard.change_language') }}</button></form>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="portal-link-button" type="submit">{{ __('dashboard.logout') }}</button></form>
            </div>
        </header>
        <main id="portal-main" tabindex="-1" class="portal-main">
            @if(session('status'))<div class="portal-status" role="status">{{ session('status') }}</div>@endif
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
