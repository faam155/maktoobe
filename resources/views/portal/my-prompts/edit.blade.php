<x-layouts.portal :title="__('prompts.edit_personal')">
<section class="portal-welcome"><p class="portal-eyebrow">{{ __('prompts.my_prompts') }}</p><h2>{{ __('prompts.edit_personal') }}</h2></section>
@if($errors->any())<div class="admin-alert is-error" role="alert">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('my-prompts.update',$prompt) }}" class="admin-form-card">@csrf @method('PUT') @include('portal.my-prompts._form')</form>
</x-layouts.portal>
