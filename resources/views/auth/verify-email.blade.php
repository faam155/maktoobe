<x-layouts.auth :title="__('auth.verify_title')">
<p class="auth-intro">{{ __('auth.verify_intro') }}</p>
<p class="auth-identifier"><bdi>{{ auth()->user()->email }}</bdi></p>
<form method="POST" action="{{ route('verification.send') }}" class="auth-form">@csrf<button class="auth-primary" type="submit">{{ __('auth.resend_verification') }}</button></form>
@if(config('identity.mail_driver')==='local')<p class="auth-hint auth-bottom">{{ __('auth.local_delivery') }}</p>@endif
</x-layouts.auth>
