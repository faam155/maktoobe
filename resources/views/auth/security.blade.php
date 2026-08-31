<x-layouts.auth :title="__('auth.security_title')" :wide="true">
<p class="auth-intro">{{ __('auth.security_intro') }}</p>
<section class="auth-section"><h2>{{ __('auth.google') }}</h2>
@if(auth()->user()->socialAccounts()->where('provider','google')->exists())<p>{{ __('auth.google_connected') }}</p>
@elseif(\App\Http\Controllers\Auth\GoogleController::configured())<form method="POST" action="{{ route('google.link') }}">@csrf<button class="auth-secondary" type="submit">{{ __('auth.google_link') }}</button></form>
@else<p class="auth-hint">{{ __('auth.google_unavailable') }}</p>@endif
</section>
<section class="auth-section"><h2>{{ __('auth.phone') }}</h2>
@if(auth()->user()->phone_e164)
<p><bdi>{{ auth()->user()->phone_e164 }}</bdi> · {{ auth()->user()->phone_verified_at ? __('auth.phone_confirmed'):__('auth.phone_pending') }}</p>
@if(!auth()->user()->phone_verified_at)<form method="POST" action="{{ route('phone.send') }}">@csrf<button class="auth-secondary" type="submit">{{ __('auth.verify_phone') }}</button></form>@endif
@else<p class="auth-hint">{{ __('auth.no_phone') }}</p>@endif
</section>
<section class="auth-section"><h2>{{ __('auth.sessions') }}</h2><p class="auth-hint">{{ __('auth.sessions_hint') }}</p>
<ul class="auth-session-list">@foreach($sessions as $session)<li><strong>{{ $session['current'] ? __('auth.current_session'):__('auth.other_session') }}</strong>
<bdi>{{ $session['ip'] }}</bdi><span>{{ \Illuminate\Support\Carbon::createFromTimestampUTC($session['last_activity'])->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }}</span>
<small>{{ \Illuminate\Support\Str::limit($session['agent'],160) }}</small></li>@endforeach</ul>
<form method="POST" action="{{ route('sessions.revoke') }}">@csrf<button class="auth-secondary" type="submit">{{ __('auth.revoke_sessions') }}</button></form>
</section>
<p class="auth-bottom"><a href="{{ route('account.home') }}">{{ __('auth.back_home') }}</a></p>
</x-layouts.auth>
