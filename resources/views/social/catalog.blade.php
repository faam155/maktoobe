<x-layouts.portal :title="__('social.'.$kind)"><div class="social-workspace">@include('social._nav')
@can('linkedin.'.$ability)<section class="social-section"><h2>{{ __('social.'.($edit?'edit':'create')) }}</h2>
<form class="social-form" method="POST" action="{{ route('social.catalog.save',[$account,$kind]) }}">@csrf
@if($edit)<input type="hidden" name="id" value="{{ $edit->id }}">@endif
@if(in_array($kind,['campaign','tag']))
@include('social._field',['name'=>'name','value'=>$edit?->name,'required'=>true])
@include('social._field',['name'=>'description','type'=>'textarea','value'=>$edit?->description])
@if($kind==='campaign')
@include('social._field',['name'=>'kind','value'=>$edit?->kind??'campaign','choices'=>['campaign'=>__('social.campaign'),'initiative'=>__('social.initiative')]])
@foreach(['starts_on','ends_on'] as $field)@include('social._field',['name'=>$field,'type'=>'date','value'=>$edit?->$field])@endforeach
@endif
@elseif($kind==='target')
@foreach(['year','month','value'] as $field)@include('social._field',['name'=>$field,'type'=>'number','value'=>$edit?->$field??($field==='year'?now()->year:0),'required'=>true])@endforeach
@include('social._field',['name'=>'metric','value'=>$edit?->metric,'choices'=>collect(['followers','new_followers','growth_rate','impressions','engagement_rate','engagements','clicks','posts'])->mapWithKeys(fn($x)=>[$x=>__('social.'.$x)])->all()])
@else
@include('social._field',['name'=>'metric_date','type'=>'date','value'=>$edit?->metric_date,'required'=>true])
@include('social._field',['name'=>'dimension','value'=>$edit?->dimension,'choices'=>collect(['location','industry','job_function','seniority'])->mapWithKeys(fn($x)=>[$x=>__('social.'.$x)])->all()])
@include('social._field',['name'=>'value','value'=>$edit?->value,'required'=>true])
@foreach(['audience_count','percentage'] as $field)@include('social._field',['name'=>$field,'type'=>'number','value'=>$edit?->$field])@endforeach
@endif
@if($kind!=='audience')@include('social._field',['name'=>'active','value'=>$edit?(int)$edit->active:1,'choices'=>[1=>__('social.active'),0=>__('social.inactive')]])@endif
<button class="social-button">{{ __('social.save') }}</button></form></section>@endcan
<div class="social-table"><table><thead><tr><th>{{ __('social.name') }}</th><th>{{ __('social.value') }}</th><th>{{ __('social.status') }}</th><th>{{ __('social.edit') }}</th></tr></thead><tbody>
@forelse($items as $item)<tr><td>{{ $item->name??($kind==='target'?__('social.'.$item->metric):$item->value) }} @if($kind==='target')<bdi>{{ $item->year }}/{{ $item->month?:__('social.annual') }}</bdi>@endif</td>
<td>{{ $kind==='audience'?($item->percentage.'% · '.$item->metric_date):($item->value??$item->description) }}</td><td>{{ $kind==='audience'?__('social.'.$item->dimension):__('social.'.($item->active?'active':'inactive')) }}</td>
<td>@can('linkedin.'.$ability)<a href="{{ route('social.catalog',[$account,$kind,'edit'=>$item->id]) }}">{{ __('social.edit') }}</a>
@if($kind!=='audience')<details><summary>{{ __('social.delete') }}</summary><form method="POST" action="{{ route('social.catalog.delete',[$account,$kind,$item]) }}">@csrf @method('DELETE')<button>{{ __('social.confirm_delete') }}</button></form></details>@endif @endcan</td></tr>
@empty<tr><td colspan="4">{{ __('social.empty') }}</td></tr>@endforelse</tbody></table></div>{{ $items->links() }}
@if($kind==='target')@can('linkedin.targets.manage')
@foreach($items->where('month',0) as $annual)<form method="POST" action="{{ route('social.targets.distribute',[$account,$annual]) }}">@csrf
<p>{{ $annual->year }} · {{ __('social.'.$annual->metric) }} · {{ $annual->value }}</p><button class="social-button secondary">{{ __('social.distribute') }}</button></form>@endforeach
@endcan @endif
</div></x-layouts.portal>
