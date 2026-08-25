<div class="d-flex align-content-center flex-wrap gap-3 mt-3 mt-md-0">
	<a href="{{ route('order.carts') }}" class="btn btn-label-warning">
		<i class="ti ti-shopping-cart me-1"></i>{{ __('Carritos abiertos') }}
		@if (($openCartsCount ?? 0) > 0)
			<span class="badge bg-warning text-dark ms-1">{{ $openCartsCount }}</span>
		@endif
	</a>
	@if ($showCreateOrder ?? true)
		@can('order.create')
			<a href="{{ route('order.create') }}" class="btn btn-primary">
				<i class="ti ti-plus me-1"></i> {{ __('Agregar Orden') }}
			</a>
		@endcan
	@endif
</div>
