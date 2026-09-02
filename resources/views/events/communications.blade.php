<x-dynamic-component :component="$administration ? 'layouts.admin' : 'layouts.portal'" :title="$event->title.' · '.__('communications.title')">
@php
    $prefix = $administration ? 'admin.events.communications' : 'events.communications';
    $direction = $slot['language']==='ar' ? 'rtl' : 'ltr';
@endphp
@include('portal.events._workspace-tabs')
<p class="report-intro">{{ __('communications.intro') }}</p>
@if(!$administration && $errors->any())<div class="admin-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<nav class="communication-slots" aria-label="{{ __('communications.select') }}">
@foreach(\App\Models\EventCommunication::TYPES as $type)
<div><strong>{{ __('communications.'.$type) }}</strong><div>@foreach(['en','ar'] as $language)<a href="{{ route($prefix.'.index',['event'=>$event,'type'=>$type,'language'=>$language]) }}" @if($slot['type']===$type && $slot['language']===$language) aria-current="page" @endif>{{ __('communications.'.$language) }}</a>@endforeach</div></div>
@endforeach
</nav>
<section class="communication-editor">
<h2>{{ __('communications.'.$slot['type']) }} · {{ __('communications.'.$slot['language']) }}</h2>
@if($communication?->archived_at)<p role="status">{{ __('communications.archived') }}</p>@endif
@if($canManage)
<form method="post" action="{{ route($prefix.'.store',$event) }}" class="communication-form" data-communication-editor>
@csrf<input type="hidden" name="type" value="{{ $slot['type'] }}"><input type="hidden" name="language" value="{{ $slot['language'] }}"><input type="hidden" name="revision_number" value="{{ $communication?->revision_number ?? 0 }}">
<label>{{ __('communications.subject') }}<input name="title" dir="{{ $direction }}" lang="{{ $slot['language'] }}" maxlength="180" value="{{ old('title',$communication?->title) }}"></label>
<label>{{ __('communications.content') }}<textarea name="content" dir="{{ $direction }}" lang="{{ $slot['language'] }}" rows="12" maxlength="20000">{{ old('content',$communication?->content) }}</textarea></label>
<div class="communication-toolbar"><label>{{ __('communications.status') }}<select name="status">@foreach(\App\Models\EventCommunication::STATUSES as $status)<option value="{{ $status }}" @selected(old('status',$communication?->status ?? 'draft')===$status)>{{ __('communications.'.$status) }}</option>@endforeach</select></label><button class="admin-button">{{ __('communications.save') }}</button></div>
</form>
@else
@if(filled($communication?->content))<h3 dir="{{ $direction }}">{{ $communication->title }}</h3><p>{{ __('communications.'.$communication->status) }}</p><div class="communication-content" dir="{{ $direction }}" lang="{{ $slot['language'] }}">{{ $communication->content }}</div>@else<p>{{ __('communications.empty') }}</p>@endif
@endif
@if(filled($communication?->content) && !$communication?->archived_at)
<div class="communication-copy"><pre id="communication-copy" hidden>{{ $communication->title }}

{{ $communication->content }}</pre><button type="button" class="admin-button" data-communication-copy="communication-copy" data-copied="{{ __('communications.copied') }}" data-failed="{{ __('communications.copy_failed') }}">{{ __('communications.copy') }}</button><span role="status"></span></div>
@endif
</section>
@if($canGenerate && !$communication?->archived_at)
<section class="communication-ai" id="ai-suggestions"><h2>{{ __('communications.ai') }}</h2><p>{{ __('communications.ai_help') }}</p>
@if(config('ai.provider')==='local')<p class="form-help">{{ __('ai.local_response') }}</p>@endif
<form method="post" action="{{ route($prefix.'.generate',$event) }}" class="communication-form">
@csrf<input type="hidden" name="type" value="{{ $slot['type'] }}"><input type="hidden" name="language" value="{{ $slot['language'] }}"><input type="hidden" name="revision_number" value="{{ $communication?->revision_number ?? 0 }}"><input type="hidden" name="client_operation_id" value="{{ \Illuminate\Support\Str::uuid() }}">
<div class="communication-options"><label>{{ __('communications.operation') }}<select name="operation">@foreach(['generate','improve','translate','regenerate'] as $operation)<option value="{{ $operation }}">{{ __('communications.'.$operation) }}</option>@endforeach</select></label><label>{{ __('communications.model') }}<select name="model" required>@foreach($models as $model)<option value="{{ $model }}">{{ $model }}</option>@endforeach</select></label></div>
<label>{{ __('communications.instructions') }}<textarea name="instructions" rows="3" maxlength="4000">{{ old('instructions') }}</textarea></label>
<label class="communication-checkbox"><input type="checkbox" name="use_brand_guidelines" value="1" @checked(old('use_brand_guidelines'))>{{ __('communications.brand') }}</label>
<button class="admin-button" @disabled(!$models)>{{ __('communications.request') }}</button>
</form>
@foreach($generations as $generation)
<article class="communication-suggestion"><h3>{{ __('communications.'.$generation->operation) }} · <bdi>{{ $generation->model }}</bdi></h3><p>{{ __('communications.requested') }} <time>{{ $generation->created_at->setTimezone(auth()->user()->timezone ?: 'UTC')->translatedFormat('j M Y H:i') }}</time></p>
<p role="status" @if(in_array($generation->status,['queued','processing'])) data-generation-status="{{ route($prefix.'.status',[$event,$generation]) }}" @endif>{{ __('communications.'.$generation->status) }}</p>
<a href="{{ url()->full() }}#ai-suggestions" class="generation-refresh" @if(in_array($generation->status,['queued','processing'])) hidden @endif>{{ __('communications.refresh') }}</a>
@if($generation->status==='failed')<p>{{ __('communications.failure') }}</p>@endif
@if($generation->status==='completed')
<h4 dir="{{ $direction }}">{{ $generation->result['title'] }}</h4><div class="communication-content" dir="{{ $direction }}" lang="{{ $slot['language'] }}">{{ $generation->result['content'] }}</div>
@if($generation->applied_at)<p>{{ __('communications.applied') }}</p>@elseif($generation->base_revision!==$communication->revision_number)<p>{{ __('communications.conflict') }}</p>@else<form method="post" action="{{ route($prefix.'.apply',[$event,$generation]) }}">@csrf<button class="admin-button">{{ __('communications.apply') }}</button></form>@endif
@endif</article>
@endforeach
</section>
@endif
@if($canManage && $communication)
<section class="communication-history"><h2>{{ __('communications.history') }}</h2>
@foreach($history as $revision)<details><summary>{{ __('communications.version',['number'=>$revision->version_number]) }} · {{ $revision->created_at->translatedFormat('j M Y H:i') }} · {{ $revision->creator?->name ?? __('event_reports.unknown') }}</summary><p>{{ __('communications.'.$revision->status) }} · {{ __('communications.'.$revision->origin) }}</p><h3 dir="{{ $direction }}">{{ $revision->title }}</h3><div class="communication-content" dir="{{ $direction }}">{{ $revision->content }}</div></details>@endforeach
{{ $history->links() }}
@if(!$communication->archived_at)<details class="communication-archive"><summary>{{ __('communications.archive') }}</summary><form method="post" action="{{ route($prefix.'.archive',[$event,$communication]) }}">@csrf @method('DELETE')<input type="hidden" name="revision_number" value="{{ $communication->revision_number }}"><label><input type="checkbox" name="confirm" value="1" required>{{ __('communications.archive_confirm') }}</label><button class="prompt-danger-button">{{ __('communications.archive') }}</button></form></details>@endif
</section>
@endif
</x-dynamic-component>
