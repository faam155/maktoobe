<x-layouts.portal :title="__('social.reports')"><div class="social-workspace">@include('social._nav')@include('social._filters')
<p class="social-note">{{ __('social.report_note') }}</p>
<form method="POST" action="{{ route('social.reports.save',[$account]+$f) }}" class="social-form">@csrf
@include('social._field',['name'=>'name','value'=>$account->name.' '.$f['from'],'required'=>true])
@include('social._field',['name'=>'summary','type'=>'textarea','value'=>$summary])
<button class="social-button">{{ __('social.save') }}</button></form>
<div class="social-actions">@foreach(['pdf','xlsx'] as $format)<a class="social-button" href="{{ route('social.export',[$account,$format]+$f) }}">{{ __($format==='pdf'?'social.export_pdf':'social.export_xlsx') }}</a>@endforeach</div>
@foreach($reports as $report)<section class="social-section"><h2>{{ $report->name }}</h2><p><bdi>{{ $report->created_at }}</bdi></p>@foreach(['pdf','xlsx'] as $format)<a class="social-button secondary" href="{{ route('social.export',[$account,$format,'report_id'=>$report->id]) }}">{{ __($format==='pdf'?'social.export_pdf':'social.export_xlsx') }}</a>@endforeach</section>@endforeach{{ $reports->links() }}
@include('social._insights')
</div></x-layouts.portal>
