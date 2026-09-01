<x-layouts.portal :title="__('prompts.create_personal')">
<section class="portal-welcome"><p class="portal-eyebrow">{{ __('prompts.my_prompts') }}</p><h2>{{ __('prompts.create_personal') }}</h2><p>{{ __('prompts.personal_private_note') }}</p></section>
@if($errors->any())<div class="admin-alert is-error" role="alert">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('my-prompts.store') }}" class="admin-form-card">@csrf @include('portal.my-prompts._form')</form>
</x-layouts.portal>
