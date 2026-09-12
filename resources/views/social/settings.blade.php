<x-layouts.portal :title="__('social.settings')"><div class="social-workspace">@include('social._nav')
<form class="social-form" method="POST" action="{{ route('social.settings.save',$account) }}">@csrf
<fieldset><legend>{{ __('social.engagement_components') }}</legend>@foreach(['reactions','comments','shares','clicks'] as $key)<label class="social-checkbox"><input type="checkbox" name="engagement_components[]" value="{{ $key }}" @checked(in_array($key,old('engagement_components',$settings['engagement_components'])))>{{ __('social.'.$key) }}</label>@endforeach<p>{{ __('social.formula_note') }}</p></fieldset>
@foreach(['score_weights','score_thresholds'] as $group)<fieldset><legend>{{ __('social.'.$group) }}</legend>@foreach($settings[$group] as $key=>$value)<label>{{ __('social.'.$key) }}<input type="number" min="0" max="100" step="0.01" name="{{ $group }}[{{ $key }}]" value="{{ old($group.'.'.$key,$value) }}" required></label>@endforeach</fieldset>@endforeach
@include('social._field',['name'=>'attention_ratio','type'=>'number','value'=>$settings['attention_ratio'],'required'=>true])
<button class="social-button">{{ __('social.save') }}</button></form></div></x-layouts.portal>
