<li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-1" wire:poll.30s="refreshOpenCount">
    <a class="nav-link" href="{{ route('ticket.index') }}" data-bs-toggle="tooltip" data-bs-placement="bottom"
        title="{{ __('Tickets') }}">
        <i class="ti ti-ticket ti-md"></i>
        @if ($openCount > 0)
            <span class="badge bg-danger rounded-pill badge-notifications">{{ $openCount > 99 ? '99+' : $openCount }}</span>
        @endif
    </a>
</li>
