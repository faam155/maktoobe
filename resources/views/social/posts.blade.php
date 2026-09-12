<x-layouts.portal :title="__('social.posts')"><div class="social-workspace">@include('social._nav')
@can('linkedin.posts.create')<a class="social-button" href="{{ route('social.posts.create',$account) }}">{{ __('social.create') }}</a>@endcan
@include('social._filters')@include('social._posts'){{ $posts->links() }}</div></x-layouts.portal>
