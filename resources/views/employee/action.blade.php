{{-- Action column template for Employee DataTable --}}
<div class="d-flex justify-content-center align-items-center">
	{{-- Edit employee --}}
	@if (auth()->user()->can('employee.edit'))
		<a href="{{ route('employee.edit', $contact->id) }}" class="text-body">
			<i class="ti ti-edit ti-sm me-2"></i>
		</a>
	@endif

	{{-- View absences/availability --}}
	@if (auth()->user()->can('employee.show'))
		<a href="{{ route('employee.absences', $contact->id) }}" class="text-body">
			<i class="ti ti-calendar ti-sm me-2"></i>
		</a>
	@endif

	{{-- View employee details --}}
	@if (auth()->user()->can('employee.show'))
		<a href="{{ route('employee.show', $contact->id) }}" class="text-body">
			<i class="ti ti-eye ti-sm me-2"></i>
		</a>
	@endif

	{{-- Delete employee --}}
	@if (auth()->user()->can('employee.destroy'))
		<a href="#" class="text-danger" onclick="deleteRecord({{ $contact->id }}, this)">
			<i class="ti ti-trash ti-sm"></i>
		</a>
	@endif
</div>
