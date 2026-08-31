@props(['title','wide'=>false])
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale()==='ar' ? 'rtl':'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} · {{ __('foundation.brand') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="auth-body">
<a class="skip-link" href="#auth-main">{{ __('foundation.skip') }}</a>
<header class="auth-header">
    <x-brand />
    <form method="POST" action="{{ route('locale.update') }}" class="auth-locale">
        @csrf
        <label class="sr-only" for="auth-locale">{{ __('auth.language') }}</label>
        <select id="auth-locale" name="locale"><option value="en" @selected(app()->getLocale()==='en')>English</option><option value="ar" @selected(app()->getLocale()==='ar')>العربية</option></select>
        <button type="submit">{{ __('auth.save_language') }}</button>
    </form>
</header>
<main id="auth-main" tabindex="-1" class="auth-panel {{ $wide ? 'auth-panel-wide':'' }}">
    <h1>{{ $title }}</h1>
    @if(session('status'))<div class="auth-status" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="auth-errors" role="alert"><strong>{{ __('auth.errors') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    {{ $slot }}
</main>
<footer class="auth-footer">
    <span>{{ __('auth.footer') }}</span>
    @auth<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="auth-text-link">{{ __('auth.logout') }}</button></form>@endauth
</footer>
</body>
</html>
