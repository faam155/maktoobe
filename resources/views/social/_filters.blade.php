<details class="social-filter-panel"><summary>{{ __('social.filters') }}</summary>
<form method="GET" class="social-form social-filter-form">
@foreach(['period'=>\App\Services\Social\SocialFilters::PERIODS,'compare'=>\App\Services\Social\SocialFilters::COMPARISONS,'language'=>['en','ar','bilingual','other'],'granularity'=>['day','week','month']] as $key=>$values)
<label>{{ __('social.'.$key) }}<select name="{{ $key }}">@if($key==='language')<option value="">{{ __('social.all') }}</option>@endif
@foreach($values as $value)<option value="{{ $value }}" @selected(($f[$key]??'')===$value)>{{ __('social.'.($key==='granularity'&&$value==='month'?'monthly':$value)) }}</option>@endforeach</select></label>
@endforeach
@foreach(['from','to'] as $key)<label>{{ __('social.'.$key) }}<input type="date" name="{{ $key }}" value="{{ $f[$key] }}"></label>@endforeach
@foreach(['content_type_id'=>'types','campaign_id'=>'campaigns','event_id'=>'events'] as $key=>$collection)
<label>{{ __('social.'.$key) }}<select name="{{ $key }}"><option value="">{{ __('social.all') }}</option>
@foreach($options[$collection] as $item)<option value="{{ $item->id }}" @selected(($f[$key]??'')==$item->id)>{{ $collection==='types'?$item->label():($item->name??$item->title) }}</option>@endforeach</select></label>
@endforeach
<label>{{ __('social.top') }}<select name="top">@foreach([5,10,20] as $n)<option @selected($f['top']==$n)>{{ $n }}</option>@endforeach</select></label>
<button class="social-button">{{ __('social.apply') }}</button>
</form></details>
