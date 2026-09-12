@can('linkedin.ai.view')<section class="social-section"><h2>{{ __('social.ai_insights') }}</h2><p class="social-note">{{ __('social.ai_note') }}</p>
@can('linkedin.ai.generate')@can('use-ai')<form method="POST" action="{{ route('social.insights.generate',[$account]+$f) }}" class="social-form">@csrf<input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::uuid() }}">
<label>{{ __('social.model') }}<select name="model">@foreach(app(\App\Services\Ai\AiModelAccess::class)->modelsFor(auth()->user()) as $model)<option>{{ $model }}</option>@endforeach</select></label><button class="social-button">{{ __('social.generate') }}</button></form>@endcan @endcan
@forelse($insights??[] as $insight)<article><p>{{ __('social.'.$insight->status) }} · <bdi>{{ $insight->created_at }}</bdi></p>@if($insight->content)<p class="social-text">{{ $insight->content }}</p>@endif
@if($insight->error_code)<p>{{ __('social.'.$insight->error_code) }}</p>@endif
@if($insight->recommendations)<h3>{{ __('social.recommendations') }}</h3><p class="social-text">{{ $insight->recommendations }}</p>@endif</article>
@empty<p>{{ __('social.empty') }}</p>@endforelse</section>@endcan
