<x-layouts.portal :title="__('notifications.title')">
    <div class="notification-heading">
        <p>{{ __('notifications.unread', ['count' => $unread]) }}</p>
        <form method="post" action="{{ route('notifications.read-all') }}">
            @csrf
            <button class="admin-button">{{ __('notifications.read_all') }}</button>
        </form>
    </div>
    <nav class="notification-filters" aria-label="{{ __('notifications.filter') }}">
        <a href="{{ route('notifications.index') }}">{{ __('notifications.all') }}</a>
        <a href="{{ route('notifications.index', ['filter' => 'unread']) }}">{{ __('notifications.unread_only') }}</a>
    </nav>
    @if($errors->any())
        <div class="admin-errors" role="alert">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif
    <div class="notification-list">
        @forelse($notifications as $item)
            @php($text = $inbox->describe($item))
            <article class="notification-item" data-unread="{{ $item->read_at ? 'false' : 'true' }}">
                <div>
                    <span>{{ __($item->read_at ? 'notifications.read' : 'notifications.new') }}</span>
                    <h2>{{ $text['title'] }}</h2>
                    <p>{{ $text['body'] }}</p>
                    <time datetime="{{ $item->created_at->toIso8601String() }}">{{ $item->created_at->setTimezone(auth()->user()->timezone ?: 'UTC')->translatedFormat('j M Y H:i') }}</time>
                </div>
                <div class="notification-actions">
                    <form method="post" action="{{ route('notifications.open', $item->id) }}">
                        @csrf
                        <button>{{ __('notifications.open') }}</button>
                    </form>
                    @if(!$item->read_at)
                        <form method="post" action="{{ route('notifications.read', $item->id) }}">
                            @csrf
                            <button>{{ __('notifications.mark_read') }}</button>
                        </form>
                    @endif
                    <form method="post" action="{{ route('notifications.dismiss', $item->id) }}">
                        @csrf
                        @method('DELETE')
                        <button>{{ __('notifications.dismiss') }}</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="notification-empty">{{ __('notifications.empty') }}</p>
        @endforelse
    </div>
    {{ $notifications->links() }}
    @can('manage-system-settings')
        <details class="notification-system">
            <summary>{{ __('notifications.system_send') }}</summary>
            <p>{{ __('notifications.system_help') }}</p>
            <form method="post" action="{{ route('notifications.system') }}" class="communication-form">
                @csrf
                <input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::uuid() }}">
                <label>{{ __('notifications.title_en') }}<input name="title_en" maxlength="120" required dir="ltr" value="{{ old('title_en') }}"></label>
                <label>{{ __('notifications.body_en') }}<textarea name="body_en" maxlength="2000" required dir="ltr">{{ old('body_en') }}</textarea></label>
                <label>{{ __('notifications.title_ar') }}<input name="title_ar" maxlength="120" required dir="rtl" value="{{ old('title_ar') }}"></label>
                <label>{{ __('notifications.body_ar') }}<textarea name="body_ar" maxlength="2000" required dir="rtl">{{ old('body_ar') }}</textarea></label>
                <label>{{ __('notifications.target') }}<input name="target_user_id" type="number" min="1" value="{{ old('target_user_id') }}"></label>
                <label class="communication-checkbox"><input type="checkbox" name="confirm" value="1" required>{{ __('notifications.confirm') }}</label>
                <button class="admin-button">{{ __('notifications.system_send') }}</button>
            </form>
        </details>
    @endcan
</x-layouts.portal>
