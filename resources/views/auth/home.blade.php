<x-layouts.auth :title="__('auth.home_title')">
<p class="auth-intro">{{ __('auth.welcome',['name'=>auth()->user()->name]) }}</p>
<p>{{ __('auth.home_intro') }}</p>
<dl class="auth-account-details"><dt>{{ __('auth.username') }}</dt><dd><bdi>{{ auth()->user()->username }}</bdi></dd><dt>{{ __('auth.email') }}</dt><dd><bdi>{{ auth()->user()->email }}</bdi></dd></dl>
<a class="auth-primary" href="{{ route('account.security') }}">{{ __('auth.security_access') }}</a>
@can('access-admin')<a class="auth-secondary auth-bottom" href="{{ route('admin.dashboard') }}">{{ __('auth.admin_access') }}</a>@endcan
</x-layouts.auth>
