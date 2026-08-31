<x-layouts.auth :title="__('auth.otp_title')">
<p class="auth-intro">{{ __('auth.otp_intro') }}</p>
<form method="POST" action="{{ route('otp.send') }}" class="auth-form">@csrf
<x-auth.field name="phone" type="tel" :label="__('auth.phone')" autocomplete="tel" maxlength="32" dir="ltr" :hint="__('auth.phone_hint')" />
<button class="auth-primary" type="submit">{{ __('auth.send_code') }}</button>
</form>
@if(config('identity.sms_driver')==='local')<p class="auth-hint auth-bottom">{{ __('auth.local_delivery') }}</p>@endif
<p class="auth-bottom"><a href="{{ route('login') }}">{{ __('auth.back_login') }}</a></p>
</x-layouts.auth>
