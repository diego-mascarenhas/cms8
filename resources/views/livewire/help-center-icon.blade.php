<li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-1" wire:poll.30s>
	<a class="nav-link dropdown-toggle hide-arrow" href="{{ route('chat.index', ['view' => 'assistant']) }}">
		<i class="ti ti-messages ti-md text-body"></i>
		@if ($inboundCount > 0)
			<span class="badge bg-danger rounded-pill badge-notifications">{{ $inboundCount }}</span>
		@endif
	</a>
</li>
