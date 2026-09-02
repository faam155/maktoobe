@php
    $editing = isset($event);
    $timezone = old('timezone', $editing ? $event->timezone : auth()->user()->timezone);
    $timezone = in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';
    $selectedUsers = collect(old('user_ids', $editing ? $event->allowedUsers->pluck('id')->all() : []))->map(fn($id)=>(int)$id)->all();
    $selectedRoles = collect(old('role_ids', $editing ? $event->allowedRoles->pluck('id')->all() : []))->map(fn($id)=>(int)$id)->all();
@endphp
<div class="event-form-grid">
    <label>{{ __('events.title') }}<input name="title" value="{{ old('title',$event->title ?? '') }}" required maxlength="180">@error('title')<small class="field-error">{{ $message }}</small>@enderror</label>
    <label>{{ __('events.category') }}<select name="category_id"><option value="">{{ __('events.no_category') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id',$event->category_id ?? '')===(string)$category->id)>{{ $category->name() }}</option>@endforeach</select></label>
    <label class="event-span">{{ __('events.description') }}<textarea name="description" rows="5" maxlength="10000">{{ old('description',$event->description ?? '') }}</textarea></label>
    <label>{{ __('events.starts_at') }}<input type="datetime-local" name="starts_at" value="{{ old('starts_at',$editing ? $event->starts_at->setTimezone($timezone)->format('Y-m-d\TH:i') : now($timezone)->addDay()->format('Y-m-d\TH:i')) }}" required>@error('starts_at')<small class="field-error">{{ $message }}</small>@enderror</label>
    <label>{{ __('events.ends_at') }}<input type="datetime-local" name="ends_at" value="{{ old('ends_at',$editing ? $event->ends_at->setTimezone($timezone)->format('Y-m-d\TH:i') : now($timezone)->addDay()->addHour()->format('Y-m-d\TH:i')) }}" required>@error('ends_at')<small class="field-error">{{ $message }}</small>@enderror</label>
    <label>{{ __('events.timezone') }}<input name="timezone" value="{{ $timezone }}" required dir="ltr"></label>
    <label>{{ __('events.location') }}<input name="location" value="{{ old('location',$event->location ?? '') }}" maxlength="255"></label>
    <label>{{ __('events.organizer') }}<select name="organizer_id" required>@foreach($users as $user)<option value="{{ $user->id }}" @selected((int)old('organizer_id',$event->organizer_id ?? auth()->id())===$user->id)>{{ $user->name }} · {{ $user->email }}</option>@endforeach</select></label>
    @unless($editing)<label>{{ __('events.status') }}<select name="status">@foreach($statuses as $status)@if(in_array($status->value,['draft','planned','confirmed']))<option value="{{ $status->value }}" @selected(old('status','draft')===$status->value)>{{ __('events.'.$status->value) }}</option>@endif @endforeach</select></label>@endunless
    <label>{{ __('events.visibility') }}<select name="visibility" required>@foreach($visibilities as $visibility)<option value="{{ $visibility->value }}" @selected(old('visibility',$event->visibility->value ?? 'private')===$visibility->value)>{{ __('events.'.$visibility->value) }}</option>@endforeach</select><small>{{ __('events.visibility_help') }}</small></label>
    <fieldset><legend>{{ __('events.assign_users') }}</legend><div class="event-choice-list">@foreach($users as $user)<label><input type="checkbox" name="user_ids[]" value="{{ $user->id }}" @checked(in_array($user->id,$selectedUsers,true))> {{ $user->name }}</label>@endforeach</div>@error('user_ids')<small class="field-error">{{ $message }}</small>@enderror</fieldset>
    <fieldset><legend>{{ __('events.assign_roles') }}</legend><div class="event-choice-list">@foreach($roles as $role)<label><input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked(in_array($role->id,$selectedRoles,true))> {{ $role->name }}</label>@endforeach</div>@error('role_ids')<small class="field-error">{{ $message }}</small>@enderror</fieldset>
</div>
<p class="form-help">{{ __('events.selection_help') }}</p><button class="admin-button" type="submit">{{ __('events.save') }}</button>
