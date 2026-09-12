<nav class="social-nav" aria-label="{{ __('social.social_media') }}">
@foreach(['dashboard'=>['dashboard.view','dashboard'],'posts'=>['posts.view','posts'],'metrics'=>['metrics.manage','metrics'],'imports'=>['import','imports'],'reports'=>['export','reports'],'sync'=>['sync','sync'],'settings'=>['settings.manage','settings']] as $route=>$item)
@can('linkedin.'.$item[0])<a href="{{ route('social.'.$route,$account) }}">{{ __('social.'.$item[1]) }}</a>@endcan
@endforeach
@foreach(['campaign'=>['campaigns.manage','campaigns'],'tag'=>['campaigns.manage','tags'],'target'=>['targets.view','targets'],'audience'=>['audience.view','audience']] as $kind=>$item)
@can('linkedin.'.$item[0])<a href="{{ route('social.catalog',[$account,$kind]) }}">{{ __('social.'.$item[1]) }}</a>@endcan
@endforeach
</nav>
<p hidden role="status" data-social-feedback data-processing="{{ __('social.processing') }}" data-completed="{{ __('social.completed') }}" data-failed="{{ __('social.export_unavailable') }}"></p>
@if($errors->any())<div class="auth-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
