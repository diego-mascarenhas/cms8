@if(auth()->user()->currentTeam)
@once
@push('page-style')
<style>
    /* Override Vuexy theme: archive/read actions hidden until hover */
    .dropdown-notifications .dropdown-notifications-item .dropdown-notifications-archive,
    .dropdown-notifications .dropdown-notifications-item .dropdown-notifications-read:not(.d-none),
    .dropdown-notifications .dropdown-notifications-item .dropdown-notifications-unread:not(.d-none) {
        visibility: visible !important;
        opacity: 1 !important;
    }
    .dropdown-notifications-item .dropdown-notifications-actions-group {
        opacity: 1;
        visibility: visible;
    }
    .dropdown-notifications-item:not(.marked-as-read) .dropdown-notifications-unread-dot {
        display: none;
    }
    .dropdown-notifications-action-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.35rem 0.5rem;
        color: var(--bs-body-color);
    }
    .dropdown-notifications-action-btn:hover {
        background-color: rgba(var(--bs-primary-rgb), 0.08);
        color: var(--bs-primary);
    }
    .dropdown-notifications-footer-links {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        width: 100%;
        gap: 0.75rem;
    }
    .dropdown-notifications-footer-links .dropdown-item {
        flex: 1 1 0;
        min-width: 0;
        white-space: nowrap;
        font-size: 0.8125rem;
    }
</style>
@endpush
@endonce
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
                        data-notification-id="{{ $notification->id }}"
                        data-created-at-label="{{ $notification->created_at->isoFormat('D MMM YYYY, HH:mm') }}">
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
                            <div class="dropdown-notifications-actions ms-2 flex-shrink-0 align-self-center">
                                <div class="dropdown-notifications-actions-group d-flex border rounded bg-body">
                                    <a href="javascript:void(0);"
                                        class="dropdown-notifications-archive dropdown-notifications-action-btn"
                                        data-dismiss-url="{{ route('notification.dismiss', $notification) }}"
                                        data-navbar-notification-tooltip="1"
                                        data-bs-title="{{ __('app.navbar_notification_dismiss') }}"
                                        aria-label="{{ __('app.navbar_notification_dismiss') }}">
                                        <i class="ti ti-trash ti-sm"></i>
                                    </a>
                                    <a href="javascript:void(0);"
                                        class="dropdown-notifications-read dropdown-notifications-action-btn border-start {{ $notification->is_read ? 'd-none' : '' }}"
                                        data-mark-read-url="{{ route('notification.mark-as-read', $notification) }}"
                                        data-navbar-notification-tooltip="1"
                                        data-bs-title="{{ __('app.navbar_notification_mark_read') }}"
                                        aria-label="{{ __('app.navbar_notification_mark_read') }}">
                                        <i class="ti ti-circle-check ti-sm"></i>
                                    </a>
                                    <a href="javascript:void(0);"
                                        class="dropdown-notifications-unread dropdown-notifications-action-btn border-start {{ $notification->is_read ? '' : 'd-none' }}"
                                        data-mark-unread-url="{{ route('notification.mark-as-unread', $notification) }}"
                                        data-navbar-notification-tooltip="1"
                                        data-bs-title="{{ __('app.navbar_notification_mark_unread') }}"
                                        aria-label="{{ __('app.navbar_notification_mark_unread') }}">
                                        <i class="ti ti-mail ti-sm"></i>
                                    </a>
                                </div>
                            </div>
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
        @php
            $showTasksFooterLink = Route::has('task.index')
                && auth()->user()->currentTeam?->hasModule('tasks');
        @endphp
        @if(Route::has('notification-list') || $showTasksFooterLink)
            <li class="dropdown-menu-footer border-top">
                <div class="d-flex justify-content-center p-2 dropdown-notifications-footer-links">
                    @if(Route::has('notification-list'))
                        <a href="{{ route('notification-list') }}" class="dropdown-item d-flex justify-content-center align-items-center px-2">
                            <i class="ti ti-speakerphone me-2 flex-shrink-0"></i>
                            <span class="text-truncate">{{ __('app.navbar_notifications_view_all') }}</span>
                        </a>
                    @endif
                    @if($showTasksFooterLink)
                        <a href="{{ route('task.index', ['view' => 'kanban']) }}" class="dropdown-item d-flex justify-content-center align-items-center px-2">
                            <i class="ti ti-layout-kanban me-2 flex-shrink-0"></i>
                            <span class="text-truncate">{{ __('app.navbar_notifications_view_all_tasks') }}</span>
                        </a>
                    @endif
                </div>
            </li>
        @endif
    </ul>
</li>
@endif
