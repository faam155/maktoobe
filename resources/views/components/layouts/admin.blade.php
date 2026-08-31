@props(['title'])
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale()==='ar' ? 'rtl':'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} · {{ __('admin.title') }} · {{ __('foundation.brand') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="admin-body">
<a class="skip-link" href="#admin-main">{{ __('foundation.skip') }}</a>
<header class="admin-mobile-header"><x-brand /><details class="admin-menu"><summary>{{ __('admin.menu') }}</summary><nav aria-label="{{ __('admin.navigation') }}">@include('admin.partials.navigation')</nav></details></header>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <x-brand />
        <nav aria-label="{{ __('admin.navigation') }}">@include('admin.partials.navigation')</nav>
        <p>{{ __('admin.signed_in_as',['name'=>auth()->user()->name]) }}</p>
    </aside>
    <div class="admin-workspace">
        <header class="admin-topbar">
            <div><p class="admin-kicker">{{ __('admin.title') }}</p><h1>{{ $title }}</h1></div>
            <div class="admin-top-actions">
                <form method="POST" action="{{ route('locale.update') }}" class="admin-locale">@csrf<label class="sr-only" for="admin-locale">{{ __('admin.language') }}</label><select id="admin-locale" name="locale"><option value="en" @selected(app()->getLocale()==='en')>English</option><option value="ar" @selected(app()->getLocale()==='ar')>العربية</option></select><button type="submit">{{ __('admin.change_language') }}</button></form>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="admin-link-button" type="submit">{{ __('admin.logout') }}</button></form>
            </div>
        </header>
        <main id="admin-main" tabindex="-1" class="admin-main">
            @if(session('status'))<div class="admin-status" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="admin-errors" role="alert"><strong>{{ __('auth.errors') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
