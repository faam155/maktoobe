<x-layouts.auth :title="__('auth.reset_title')">
<p class="auth-intro">{{ __('auth.reset_intro') }}</p>
<form method="POST" action="{{ route('password.update') }}" class="auth-form">@csrf
<input type="hidden" name="token" value="{{ $request->route('token') }}">
<x-auth.field name="email" type="email" :label="__('auth.email')" autocomplete="email" :value="$request->email" dir="ltr" maxlength="254" />
<x-auth.field name="password" type="password" :label="__('auth.password')" autocomplete="new-password" maxlength="72" :hint="__('auth.password_hint')" />
<x-auth.field name="password_confirmation" type="password" :label="__('auth.password_confirmation')" autocomplete="new-password" maxlength="72" />
<button class="auth-primary" type="submit">{{ __('auth.reset_title') }}</button>
</form>
</x-layouts.auth>
