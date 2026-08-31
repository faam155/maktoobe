@props(['status'])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Enums\Locale::resolve(app()->getLocale())->direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $status }} · {{ __('foundation.brand') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite('resources/css/app.css')
</head>
<body>
    <main class="error-page">
        <x-brand />
        <p class="eyebrow">{{ $status }}</p>
        <h1>{{ __('errors.'.$status.'_title') }}</h1>
        <p class="hero-description">{{ __('errors.'.$status.'_body') }}</p>
        <a href="{{ route('foundation') }}" class="button button-primary">{{ __('errors.back') }}</a>
    </main>
</body>
</html>
