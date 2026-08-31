<x-layouts.auth :title="__('auth.confirm_title')">
<p class="auth-intro">{{ __('auth.confirm_intro') }}</p>
@if(auth()->user()->password)
<form method="POST" action="{{ route('password.confirm.store') }}" class="auth-form">@csrf
<x-auth.field name="password" type="password" :label="__('auth.password')" autocomplete="current-password" maxlength="128" />
<button class="auth-primary" type="submit">{{ __('auth.confirm_password') }}</button>
</form>
@else<p>{{ __('auth.passwordless_reauth') }}</p>@endif
</x-layouts.auth>
