<x-layouts.portal :title="__('social.imports')"><div class="social-workspace">@include('social._nav')
<p class="social-note">{{ __('social.import_note') }}</p><a href="{{ route('social.imports.template',$account) }}">{{ __('social.template') }}</a>
<form class="social-form" method="POST" enctype="multipart/form-data" action="{{ route('social.imports.preview',$account) }}">@csrf<label>{{ __('social.file') }}<input name="file" type="file" accept=".csv,.xlsx" required></label><button class="social-button">{{ __('social.preview') }}</button></form>
@foreach($imports as $import)<section class="social-section"><h2>#{{ $import->id }} · {{ __('social.'.$import->status) }}</h2><p>{{ $import->rows }} {{ __('social.rows') }} · <bdi>{{ $import->created_at }}</bdi></p>
@if($import->errors)<div class="auth-errors" role="alert">@foreach($import->errors as $error)<p>{{ $error['row'] }}: {{ collect($error['messages'])->flatten()->join(' ') }}</p>@endforeach</div>@endif
<details><summary>{{ __('social.preview') }}</summary><div class="social-table"><table><thead><tr>@foreach(\App\Actions\Social\ImportSocialMetrics::HEADER as $key)<th>{{ __('social.'.$key) }}</th>@endforeach</tr></thead><tbody>
@foreach($import->preview as $row)<tr>@foreach(\App\Actions\Social\ImportSocialMetrics::HEADER as $key)<td>{{ $row[$key]??'—' }}</td>@endforeach</tr>@endforeach</tbody></table></div></details>
@if($import->status==='preview'&&!$import->errors)<form method="POST" action="{{ route('social.imports.confirm',[$account,$import]) }}">@csrf<button class="social-button">{{ __('social.confirm') }}</button></form>@endif
</section>@endforeach{{ $imports->links() }}</div></x-layouts.portal>
