{{-- Action column template for User DataTables --}}
<div class="d-flex justify-content-center align-items-center">
	{{-- View User --}}
	@if (auth()->user()->hasRole(['admin', 'developer']) || auth()->id() == $id)
		<a href="{{ route('user.show', $id) }}" class="text-body">
			<i class="ti ti-eye ti-sm me-2"></i>
		</a>
	@endif

	{{-- Edit User --}}
	@if (auth()->user()->hasRole(['admin', 'developer']) || auth()->id() == $id)
		<a href="{{ route('user.edit', $id) }}" class="text-body">
			<i class="ti ti-edit ti-sm me-2"></i>
		</a>
	@endif
</div>
