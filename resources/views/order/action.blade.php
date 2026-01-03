{{-- Action column template for DataTables --}}
<div class="d-flex justify-content-center align-items-center">
	@can('order.show')
		<a href="{{ route('order.show', $order->id) }}" class="text-body">
			<i class="ti ti-eye ti-sm me-2"></i>
		</a>
	@endcan
	
	@can('order.edit')
		<a href="{{ route('order.edit', $order->id) }}" class="text-body">
			<i class="ti ti-edit ti-sm me-2"></i>
		</a>
	@endcan
	
	@can('order.destroy')
		<a href="#" class="text-danger" onclick="deleteOrder({{ $order->id }}, this)">
			<i class="ti ti-trash ti-sm"></i>
		</a>
	@endcan
</div>

