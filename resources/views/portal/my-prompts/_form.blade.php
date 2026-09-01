@php($prompt=$prompt??null)
<div class="admin-form-grid">
<label>{{ __('prompts.title') }}<input name="title" value="{{ old('title',$prompt?->title) }}" maxlength="160" required></label>
<label>{{ __('prompts.slug') }}<input name="slug" value="{{ old('slug',$prompt?->slug) }}" maxlength="180" dir="ltr" pattern="[a-z0-9]+(?:-[a-z0-9]+)*"><small>{{ __('prompts.slug_optional') }}</small></label>
<label>{{ __('prompts.category') }}<select name="category_id"><option value="">{{ __('prompts.no_category') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id',$prompt?->category_id)===(string)$category->id)>{{ $category->localizedName() }}</option>@endforeach</select></label>
<label>{{ __('prompts.content_locale') }}<input name="content_locale" value="{{ old('content_locale',$prompt?->content_locale) }}" maxlength="10" dir="ltr" placeholder="en"></label>
<label class="admin-field-wide">{{ __('prompts.description') }}<textarea name="description" maxlength="2000">{{ old('description',$prompt?->description) }}</textarea></label>
<label class="admin-field-wide">{{ __('prompts.content') }}<textarea name="content" maxlength="100000" required class="prompt-content-input">{{ old('content',$prompt?->content) }}</textarea></label>
<label>{{ __('prompts.tags') }}<input name="tags" value="{{ old('tags',$prompt?->tags?->pluck('display_name')->join(', ')) }}" maxlength="500"><small>{{ __('prompts.tags_hint') }}</small></label>
</div>
<p class="personal-privacy-note">{{ __('prompts.personal_private_note') }}</p>
<div class="admin-form-actions"><button class="admin-button">{{ __('prompts.save_personal') }}</button><a href="{{ route('my-prompts.index') }}">{{ __('categories.cancel') }}</a></div>
