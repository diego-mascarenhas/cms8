<div wire:poll.30s>
	<li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-1">
		<a class="nav-link dropdown-toggle hide-arrow" href="{{ route('chat.index') }}">
			<i class="ti ti-lifebuoy ti-md"></i>
			@if ($inboundCount > 0)
				<span class="badge bg-danger rounded-pill badge-notifications">{{ $inboundCount }}</span>
			@endif
		</a>
	</li>
</div>
