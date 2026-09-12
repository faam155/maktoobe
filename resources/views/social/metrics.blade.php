<x-layouts.portal :title="__('social.metrics')"><div class="social-workspace">@include('social._nav')
<p class="social-note">{{ __('social.interval_note') }} {{ __('social.daily_monthly') }}</p>
<form method="POST" action="{{ route('social.metrics.save',$account) }}" class="social-form">@csrf
@include('social._field',['name'=>'subject','choices'=>['account'=>__('social.account'),'post'=>__('social.post')]])
@include('social._field',['name'=>'post_id','choices'=>[''=>__('social.account')]+$posts->pluck('title','id')->all()])
@foreach(['starts_on','ends_on'] as $field)@include('social._field',['name'=>$field,'type'=>'date','required'=>true])@endforeach
@foreach(['impressions','reactions','comments','shares','clicks','followers','new_followers'] as $field)@include('social._field',['name'=>$field,'type'=>'number'])@endforeach
<button class="social-button">{{ __('social.save') }}</button></form>
@foreach(['account'=>$metrics,'post'=>$postMetrics] as $subject=>$rows)<section class="social-section"><h2>{{ __('social.'.$subject) }}</h2><div class="social-table"><table><thead><tr>@foreach(['starts_on','ends_on','impressions','reactions','comments','shares','clicks','revision'] as $key)<th>{{ __('social.'.$key) }}</th>@endforeach</tr></thead><tbody>
@foreach($rows as $row)<tr>@foreach(['starts_on','ends_on','impressions','reactions','comments','shares','clicks','revision'] as $key)<td>{{ $row->$key??'—' }}</td>@endforeach</tr>@endforeach</tbody></table></div>{{ $rows->links() }}</section>@endforeach
</div></x-layouts.portal>
