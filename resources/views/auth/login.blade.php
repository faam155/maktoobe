<x-layouts.auth :title="__('auth.login_title')">
<p class="auth-intro">{{ __('auth.login_intro') }}</p>
<form method="POST" action="{{ route('login.store') }}" class="auth-form">
    @csrf
    <x-auth.field name="login" :label="__('auth.login_identifier')" autocomplete="username" maxlength="254" dir="ltr" />
    <x-auth.field name="password" type="password" :label="__('auth.password')" autocomplete="current-password" maxlength="128" />
    <div class="auth-form-row"><label class="auth-checkbox"><input type="checkbox" name="remember" value="1" @checked(old('remember'))>{{ __('auth.remember') }}</label><a href="{{ route('password.request') }}">{{ __('auth.forgot') }}</a></div>
    <button class="auth-primary" type="submit">{{ __('auth.login_title') }}</button>
</form>
<div class="auth-alternatives">
@if(\App\Http\Controllers\Auth\GoogleController::configured())<a class="auth-secondary" href="{{ route('google.redirect') }}">{{ __('auth.google') }}</a>@else<p class="auth-hint">{{ __('auth.google_unavailable') }}</p>@endif
<a class="auth-secondary" href="{{ route('otp.request') }}">{{ __('auth.otp_login') }}</a>
</div>
<p class="auth-bottom">{{ __('auth.no_account') }} <a href="{{ route('register') }}">{{ __('auth.register_title') }}</a></p>
</x-layouts.auth>
