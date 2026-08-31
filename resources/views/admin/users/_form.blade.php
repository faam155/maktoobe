<div class="admin-form-grid">
<label>{{ __('admin.name') }}<input name="name" value="{{ old('name',$managedUser->name ?? '') }}" required maxlength="150" autocomplete="name"></label>
<label>{{ __('admin.username') }}<input name="username" value="{{ old('username',$managedUser->username ?? '') }}" required maxlength="32" dir="ltr" autocomplete="username"></label>
<label>{{ __('admin.email') }}<input type="email" name="email" value="{{ old('email',$managedUser->email ?? '') }}" required maxlength="254" dir="ltr" autocomplete="email"></label>
<label>{{ __('admin.phone') }} ({{ __('admin.optional') }})<input type="tel" name="phone" value="{{ old('phone',$managedUser->phone_e164 ?? '') }}" maxlength="32" dir="ltr" autocomplete="tel"></label>
@if(!isset($managedUser))<label>{{ __('admin.password') }}<input type="password" name="password" required maxlength="72" autocomplete="new-password"></label><label>{{ __('admin.password_confirmation') }}<input type="password" name="password_confirmation" required maxlength="72" autocomplete="new-password"></label>@endif
<label>{{ __('admin.locale') }}<select name="locale" required><option value="en" @selected(old('locale',$managedUser->locale??'en')==='en')>English</option><option value="ar" @selected(old('locale',$managedUser->locale??'en')==='ar')>العربية</option></select></label>
@if(!isset($managedUser))<label>{{ __('admin.status') }}<select name="status" required><option value="pending" @selected(old('status')==='pending')>{{ __('admin.statuses.pending') }}</option><option value="active" @selected(old('status')==='active')>{{ __('admin.statuses.active') }}</option></select></label>@endif
</div>
