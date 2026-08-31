<x-layouts.auth :title="__('auth.register_title')" :wide="true">
<p class="auth-intro">{{ __('auth.register_intro') }}</p>
<form method="POST" action="{{ route('register.store') }}" class="auth-form">
@csrf
<div class="auth-grid">
<x-auth.field name="name" :label="__('auth.name')" autocomplete="name" maxlength="150" />
<x-auth.field name="username" :label="__('auth.username')" autocomplete="username" maxlength="32" dir="ltr" :hint="__('auth.username_hint')" />
<x-auth.field name="email" type="email" :label="__('auth.email')" autocomplete="email" maxlength="254" dir="ltr" />
<x-auth.field name="phone" type="tel" :label="__('auth.phone').' ('.__('auth.optional').')'" autocomplete="tel" maxlength="32" dir="ltr" :required="false" :hint="__('auth.phone_hint')" />
<x-auth.field name="password" type="password" :label="__('auth.password')" autocomplete="new-password" maxlength="72" :hint="__('auth.password_hint')" />
<x-auth.field name="password_confirmation" type="password" :label="__('auth.password_confirmation')" autocomplete="new-password" maxlength="72" />
</div>
<button class="auth-primary" type="submit">{{ __('auth.create_account') }}</button>
</form>
<p class="auth-bottom">{{ __('auth.have_account') }} <a href="{{ route('login') }}">{{ __('auth.login_title') }}</a></p>
</x-layouts.auth>
