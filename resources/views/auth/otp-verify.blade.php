<x-layouts.auth :title="__('auth.otp_verify_title')">
<p class="auth-intro">{{ __('auth.otp_verify_intro') }}</p>
<form method="POST" action="{{ route($purpose==='enroll' ? 'phone.check':'otp.check') }}" class="auth-form">@csrf
<x-auth.field name="code" :label="__('auth.code')" autocomplete="one-time-code" inputmode="numeric" maxlength="6" minlength="6" pattern="[0-9]{6}" dir="ltr" />
<button class="auth-primary" type="submit">{{ __('auth.verify_code') }}</button>
</form>
<p class="auth-bottom"><a href="{{ route($purpose==='enroll' ? 'account.security':'otp.request') }}">{{ __('auth.resend_code') }}</a></p>
</x-layouts.auth>
