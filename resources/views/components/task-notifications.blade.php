@if(auth()->user()->currentTeam)
<li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-1"
    data-read-at-template="{{ __('app.navbar_notification_read_at', ['date' => '__DATE__']) }}">
    <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown"
        data-bs-auto-close="outside" aria-expanded="false">
        <i class="ti ti-bell ti-md"></i>
        @if($unreadCount > 0)
            <span class="badge bg-danger rounded-pill badge-notifications">{{ $unreadCount }}</span>
        @endif
    </a>
    <ul class="dropdown-menu dropdown-menu-end py-0">
        <li class="dropdown-menu-header border-bottom">
            <div class="dropdown-header d-flex align-items-center justify-content-between py-3">
                <div>
                    <h5 class="text-body mb-1">{{ __('Notifications') }}</h5>
                    <p class="text-muted small mb-0">{{ __('app.navbar_notifications_lead') }}</p>
                </div>
                @if($unreadCount > 0)
                    <a href="javascript:void(0);"
                        class="dropdown-notifications-all small text-primary text-nowrap ms-3"
                        data-mark-all-read-url="{{ route('notification.mark-all-as-read') }}"
                        title="{{ __('app.navbar_notification_mark_all_read') }}">
                        {{ __('app.navbar_notification_mark_all_read') }}
                    </a>
                @endif
            </div>
        </li>
        <li class="dropdown-notifications-list scrollable-container">
            <ul class="list-group list-group-flush">
                @forelse($notifications as $notification)
                    <li class="list-group-item list-group-item-action dropdown-notifications-item {{ $notification->is_read ? 'marked-as-read' : '' }}"
                        data-notification-id="{{ $notification->id }}">
                        <div class="d-flex align-items-start">
                            <div class="flex-grow-1 min-w-0">
                                <a href="{{ route('notification.show', $notification) }}" class="d-flex text-body text-decoration-none">
                                    <div class="flex-shrink-0 me-3">
                                        <div class="avatar">
                                            <span class="avatar-initial rounded-circle bg-label-{{ $notification->getAvatarColor() }}">
                                                {{ $notification->getAvatarInitials() }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <h6 class="mb-1 text-truncate">{{ $notification->subject }}</h6>
                                        <p class="mb-1 small text-muted text-truncate">{{ Str::limit(strip_tags($notification->message), 80) }}</p>
                                    </div>
                                </a>
                                <small class="text-muted d-block ps-5 ms-3 navbar-notification-date" data-notification-date>
                                    @if($notification->is_read && $notification->read_at)
                                        {{ __('app.navbar_notification_read_at', ['date' => $notification->formatted_read_at]) }}
                                    @else
                                        {{ $notification->created_at->isoFormat('D MMM YYYY, HH:mm') }}
                                    @endif
                                </small>
                            </div>
                            @if(! $notification->is_read)
                                <div class="dropdown-notifications-actions ms-2">
                                    <a href="javascript:void(0);"
                                        class="dropdown-notifications-read"
                                        data-mark-read-url="{{ route('notification.mark-as-read', $notification) }}"
                                        title="{{ __('app.navbar_notification_mark_read') }}">
                                        <span class="badge badge-dot"></span>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="list-group-item list-group-item-action dropdown-notifications-item">
                        <div class="d-flex">
                            <div class="flex-grow-1 text-center py-3">
                                <p class="mb-0">{{ __('app.navbar_notifications_empty') }}</p>
                            </div>
                        </div>
                    </li>
                @endforelse
            </ul>
        </li>
        @if(Route::has('notification-list'))
            <li class="dropdown-menu-footer border-top">
                <div class="d-flex justify-content-center p-2">
                    <a href="{{ route('notification-list') }}" class="dropdown-item d-flex justify-content-center align-items-center px-3">
                        <i class="ti ti-list me-2"></i>
                        {{ __('app.navbar_notifications_view_all') }}
                    </a>
                </div>
            </li>
        @endif
    </ul>
</li>
@endif
