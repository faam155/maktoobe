<x-layouts.auth :title="__('auth.pending_title')">
<p class="auth-intro">{{ __('auth.pending_intro') }}</p>
@if(!auth()->user()->hasVerifiedEmail())
<a class="auth-primary" href="{{ route('verification.notice') }}">{{ __('auth.verify_title') }}</a>
@else<p>{{ __('auth.pending_verified') }}</p>@endif
</x-layouts.auth>
