@php
    $notificationInbox = app(\App\Queries\Notifications\NotificationInbox::class);
    $notificationCount = $notificationInbox->unread(auth()->user());
    $notificationPreview = $notificationInbox->query(auth()->user())->with(['notice.event', 'notice.prompt'])->latest()->limit(5)->get();
@endphp
<details class="notification-panel">
    <summary aria-label="{{ __('notifications.indicator', ['count' => $notificationCount]) }}">
        {{ __('notifications.title') }} <span class="notification-count">{{ $notificationCount }}</span>
    </summary>
    <div class="notification-dropdown">
        <strong>{{ __('notifications.unread', ['count' => $notificationCount]) }}</strong>
        @forelse($notificationPreview as $item)
            @php($notificationText = $notificationInbox->describe($item))
            <div class="notification-preview">
                <p>{{ $notificationText['body'] }}</p>
                <strong>{{ $notificationText['title'] }}</strong>
                <form method="post" action="{{ route('notifications.open', $item->id) }}">
                    @csrf
                    <button>{{ __('notifications.open') }}</button>
                </form>
            </div>
        @empty
            <p>{{ __('notifications.empty') }}</p>
        @endforelse
        <a href="{{ route('notifications.index') }}">{{ __('notifications.view_all') }}</a>
    </div>
</details>
