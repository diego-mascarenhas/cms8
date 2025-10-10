<div class="d-flex justify-content-center align-items-center">
	@can('time.edit')
		@if($end_time)
			<a href="{{ route('time.edit', $id) }}" class="text-body">
				<i class="ti ti-edit ti-sm me-2"></i>
			</a>
		@else
			<span class="text-muted" title="{{ __('Cannot edit running timer') }}">
				<i class="ti ti-edit ti-sm me-2"></i>
			</span>
		@endif
	@endcan

	@can('time.destroy')
		<a href="#" class="text-danger" onclick="deleteRecord({{ $id }}, this)">
			<i class="ti ti-trash ti-sm"></i>
		</a>
	@endcan
</div>
