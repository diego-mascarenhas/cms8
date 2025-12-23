{{-- Action column template for Employee DataTable --}}
<div class="d-flex justify-content-center align-items-center">
	{{-- Edit employee --}}
	@can('update', $contact)
		<a href="{{ route('employee.edit', $contact->id) }}" class="text-body">
			<i class="ti ti-edit ti-sm me-2"></i>
		</a>
	@endcan

	{{-- View absences/availability --}}
	@can('view', $contact)
		<a href="{{ route('employee.absences', $contact->id) }}" class="text-body">
			<i class="ti ti-calendar ti-sm me-2"></i>
		</a>
	@endcan

	{{-- View employee details --}}
	@can('view', $contact)
		<a href="{{ route('employee.show', $contact->id) }}" class="text-body">
			<i class="ti ti-eye ti-sm me-2"></i>
		</a>
	@endcan

	{{-- Delete employee --}}
	@can('delete', $contact)
		<a href="#" class="text-danger" onclick="deleteRecord({{ $contact->id }}, this)">
			<i class="ti ti-trash ti-sm"></i>
		</a>
	@endcan
</div>
