<x-layouts.portal :title="__('social.accounts')"><div class="social-workspace">
@if($errors->any())<div class="auth-errors" role="alert">{{ $errors->first() }}</div>@endif
<a href="{{ route('social.index') }}">{{ __('social.dashboard') }}</a>
@foreach($accounts as $account)
<section class="social-section"><h2>{{ $account->name }}</h2>
<form class="social-form" method="POST" action="{{ route('social.accounts.save') }}">@csrf<input type="hidden" name="id" value="{{ $account->id }}">
@foreach(['name','identifier','url','timezone'] as $field)@include('social._field',['name'=>$field,'value'=>$account->$field,'required'=>in_array($field,['name','identifier','timezone'])])@endforeach
@include('social._field',['name'=>'active','value'=>(int)$account->active,'choices'=>[1=>__('social.active'),0=>__('social.inactive')]])
<button class="social-button">{{ __('social.save') }}</button></form></section>@endforeach
{{ $accounts->links() }}
<section class="social-section"><h2>{{ __('social.create') }}</h2><form class="social-form" method="POST" action="{{ route('social.accounts.save') }}">@csrf
@foreach(['name','identifier','url','timezone'] as $field)@include('social._field',['name'=>$field,'value'=>$field==='timezone'?'Asia/Muscat':'','required'=>in_array($field,['name','identifier','timezone'])])@endforeach
<input type="hidden" name="active" value="1"><button class="social-button">{{ __('social.create') }}</button>
</form></section></div></x-layouts.portal>
