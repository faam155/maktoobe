<x-layouts.portal :title="__('social.title')"><div class="social-workspace">
@can('linkedin.settings.manage')<a class="social-button" href="{{ route('social.accounts') }}">{{ __('social.accounts') }}</a>@endcan
@forelse($accounts as $account)<section class="social-section"><h2><a href="{{ route('social.dashboard',$account) }}">{{ $account->name }}</a></h2><p>{{ $account->identifier }}</p></section>
@empty<p class="social-empty">{{ __('social.no_accounts') }}</p>@endforelse
</div></x-layouts.portal>
