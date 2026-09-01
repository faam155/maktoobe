@php($category=$category??null)
<div class="admin-form-grid">
<label>{{ __('categories.name_en') }}<input name="name_en" value="{{ old('name_en',$category?->name_en) }}" maxlength="100" required dir="ltr" autocomplete="off"></label>
<label>{{ __('categories.name_ar') }}<input name="name_ar" value="{{ old('name_ar',$category?->name_ar) }}" maxlength="100" required dir="rtl" autocomplete="off"></label>
<label>{{ __('categories.slug') }}<input name="slug" value="{{ old('slug',$category?->slug) }}" maxlength="120" dir="ltr" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="{{ __('categories.slug_hint') }}"></label>
<label>{{ __('categories.icon') }}<input name="icon" value="{{ old('icon',$category?->icon) }}" maxlength="50" dir="ltr" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="folder"></label>
<label class="admin-field-wide">{{ __('categories.description_en') }}<textarea name="description_en" maxlength="2000" dir="ltr">{{ old('description_en',$category?->description_en) }}</textarea></label>
<label class="admin-field-wide">{{ __('categories.description_ar') }}<textarea name="description_ar" maxlength="2000" dir="rtl">{{ old('description_ar',$category?->description_ar) }}</textarea></label>
</div>
<label class="admin-checkbox"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool)old('is_active',$category?->is_active ?? true))> {{ __('categories.active_label') }}</label>
<div class="admin-form-actions"><button class="admin-button" type="submit">{{ $category ? __('categories.save'):__('categories.create') }}</button><a href="{{ route('admin.prompt-categories.index') }}">{{ __('categories.cancel') }}</a></div>
