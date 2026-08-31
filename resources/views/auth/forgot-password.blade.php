<x-layouts.auth :title="__('auth.forgot_title')">
<p class="auth-intro">{{ __('auth.forgot_intro') }}</p>
<form method="POST" action="{{ route('password.email') }}" class="auth-form">@csrf
<x-auth.field name="email" type="email" :label="__('auth.email')" autocomplete="email" maxlength="254" dir="ltr" />
<button class="auth-primary" type="submit">{{ __('auth.send_reset') }}</button>
</form>
<p class="auth-bottom"><a href="{{ route('login') }}">{{ __('auth.back_login') }}</a></p>
</x-layouts.auth>
