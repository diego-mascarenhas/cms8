<div class="d-flex justify-content-center align-items-center">
	@role('admin|collaborator|developer|technical')
		@if($end_time)
			<a href="{{ route('time.edit', $id) }}" class="text-body">
				<i class="ti ti-edit ti-sm me-2"></i>
			</a>
		@else
			<span class="text-muted" title="{{ __('Cannot edit running timer') }}">
				<i class="ti ti-edit ti-sm me-2"></i>
			</span>
		@endif
	@endrole

	@role('admin')
		<a href="#" class="text-danger" onclick="deleteRecord({{ $id }}, this)">
			<i class="ti ti-trash ti-sm"></i>
		</a>
	@endrole
</div>
