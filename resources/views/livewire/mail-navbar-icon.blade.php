<li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-1" wire:poll.30s="refreshUnreadCount">
    <a class="nav-link" href="{{ route('mail-list') }}" data-bs-toggle="tooltip" data-bs-placement="bottom"
        title="{{ __('Mail') }}">
        <i class="ti ti-mail ti-md"></i>
        @if ($unreadCount > 0)
            <span class="badge bg-danger rounded-pill badge-notifications">{{ $unreadCount }}</span>
        @endif
    </a>
</li>
