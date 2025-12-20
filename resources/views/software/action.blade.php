<div class="d-inline-flex">
	@can('update', $software)
		<a href="{{ route('software.edit', ['software' => $software->id]) }}" class="btn btn-sm btn-icon item-edit me-1">
			<i class="ti ti-edit ti-sm me-2"></i>
		</a>
	@endcan
</div>