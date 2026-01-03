{{-- Action column template for DataTables --}}
<div class="d-flex justify-content-center align-items-center">
	@can('product.show')
		<a href="{{ route('product.show', $product->id) }}" class="text-body">
			<i class="ti ti-eye ti-sm me-2"></i>
		</a>
	@endcan
	
	@can('product.edit')
		<a href="{{ route('product.edit', $product->id) }}" class="text-body">
			<i class="ti ti-edit ti-sm me-2"></i>
		</a>
	@endcan
	
	@can('product.destroy')
		<a href="#" class="text-danger" onclick="deleteProduct({{ $product->id }}, this)">
			<i class="ti ti-trash ti-sm"></i>
		</a>
	@endcan
</div>

