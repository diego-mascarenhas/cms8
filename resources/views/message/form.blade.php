@extends('layouts/layoutMaster')

@section('title', 'Messages')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}">
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/form-layouts.js')}}"></script>

<script>
// Delete message function with Sweet Alert - Updated v2
function deleteMessage(messageId) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "¿Deseas eliminar este mensaje?",
        icon: 'warning',
        showCancelButton: true,
        showDenyButton: false,
        confirmButtonColor: '#7367f0',
        cancelButtonColor: '#a8aaae',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            // Create a form to submit the delete request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/message/${messageId}`;
            
            // Add CSRF token
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            form.appendChild(csrfToken);
            
            // Add method override for DELETE
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'DELETE';
            form.appendChild(methodInput);
            
            // Submit the form
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

<script>
// Time preset functionality
function setTimePreset(value, unit) {
    const input = document.getElementById('min_hours_between_emails');
    const select = document.getElementById('time_unit');

    // Convert to hours based on unit
    let hours = value;
    if (unit === 'days') {
        hours = value * 24;
    } else if (unit === 'weeks') {
        hours = value * 24 * 7;
    }

    input.value = hours;
    select.value = 'hours';

    // Update button states
    document.querySelectorAll('.btn-group .btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
}

// Convert time units when selector changes
document.getElementById('time_unit').addEventListener('change', function() {
    const input = document.getElementById('min_hours_between_emails');
    const currentValue = parseInt(input.value) || 0;

    if (this.value === 'days') {
        // Convert hours to days
        input.value = Math.round(currentValue / 24);
        input.setAttribute('step', '0.5');
    } else if (this.value === 'weeks') {
        // Convert hours to weeks
        input.value = Math.round(currentValue / (24 * 7) * 10) / 10;
        input.setAttribute('step', '0.1');
    } else {
        // Convert back to hours
        if (this.previousElementSibling.value === 'days') {
            input.value = currentValue * 24;
        } else if (this.previousElementSibling.value === 'weeks') {
            input.value = Math.round(currentValue * 24 * 7);
        }
        input.setAttribute('step', '1');
    }
});

// Convert to hours before form submission
document.querySelector('form').addEventListener('submit', function() {
    const input = document.getElementById('min_hours_between_emails');
    const unit = document.getElementById('time_unit').value;
    const value = parseFloat(input.value) || 0;

    let hours = value;
    if (unit === 'days') {
        hours = value * 24;
    } else if (unit === 'weeks') {
        hours = value * 24 * 7;
    }

    // Create hidden input with hours value
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'min_hours_between_emails';
    hiddenInput.value = Math.round(hours);

    // Remove name from visible input to avoid conflict
    input.removeAttribute('name');

    // Add hidden input to form
    this.appendChild(hiddenInput);
});
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">Messages/</span> {{ isset($data->id) ? 'Edit' : 'Create' }}</h4>
        <p class="text-muted">Manage your messages with ease and keep your audience engaged!</p>
    </div>
    @if(isset($data->id))
    <div class="d-flex align-content-center flex-wrap gap-3">
        <button type="button" class="btn btn-danger" onclick="deleteMessage({{ $data->id }})">
            <i class="ti ti-trash me-1"></i>Delete Message
        </button>
    </div>
    @endif
</div>

<div class="card mb-4">
	<h5 class="card-header">Messages</h5>
	<form class="card-body" action="{{ route('message.store') }}" method="POST">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">

		<div class="row g-3">
			<div class="col-md-6">
				<x-input-general id="name" label="Name (*)" value="{{ old('name', $data->name?? '') }}" />
			</div>
			<div class="col-md-4">
				<x-module-categories-select
					id="category_id"
					label="Categoría"
					moduleKey="contacts"
					:selected="old('category_id', $data->category_id ?? '')"
					:allowEmpty="true"
					emptyText="Toda la base de datos"
				/>
			</div>
			<div class="col-md-2">
				<x-input-select
					id="contact_status_id"
					label="{{ __('Contact Status') }}"
					:options="$data->contactStatuses ?? []"
					value="{{ old('contact_status_id', $data->contact_status_id ?? '') }}"
				/>
			</div>
			<div class="col-md-6">
				<x-input-select id="type_id" label="Type (*)" :options="$data->types" value="{{ old('type_id', $data->type_id ?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-select id="template_id" label="Template" :options="$data->templates ?? []" value="{{ old('template_id', $data->template_id ?? '') }}" />
				<div class="form-text mt-1">
					¿No encuentras el template que buscas? <a href="{{ route('template.create') }}">Agregar nuevo template</a>
				</div>
			</div>
			<div class="col-md-12">
				<x-input-textarea id="text" label="Text (*)" value="{{ old('text', $data->text?? '') }}" />
			</div>
						<div class="col-md-6">
				<label for="min_hours_between_emails" class="form-label">Minimum Time Between Emails</label>
				<div class="input-group">
					<input
						type="number"
						class="form-control"
						id="min_hours_between_emails"
						name="min_hours_between_emails"
						min="0"
						step="1"
						value="{{ old('min_hours_between_emails', $data->min_hours_between_emails ?? 48) }}"
					>
					<select class="form-select" id="time_unit" name="time_unit" style="max-width: 120px;">
						<option value="hours" selected>Hours</option>
						<option value="days">Days</option>
						<option value="weeks">Weeks</option>
					</select>
				</div>
				<div class="form-text mt-1">
					Time to wait before sending another email to the same contact
				</div>
			</div>
			<div class="col-md-6">
				<label class="form-label">Quick Presets</label>
				<div class="btn-group d-flex" role="group">
					<button type="button" class="btn btn-outline-secondary btn-sm" onclick="setTimePreset(0, 'hours')">Immediate</button>
					<button type="button" class="btn btn-outline-secondary btn-sm" onclick="setTimePreset(24, 'hours')">1 Day</button>
					<button type="button" class="btn btn-outline-secondary btn-sm" onclick="setTimePreset(48, 'hours')">2 Days</button>
					<button type="button" class="btn btn-outline-secondary btn-sm" onclick="setTimePreset(1, 'weeks')">1 Week</button>
				</div>
			</div>
		</div>

		<div class="row g-3 mt-3">
			<div class="col-md-6">
				<div class="card">
					<div class="card-header">
						<h6 class="card-title mb-0">General Options</h6>
					</div>
					<div class="card-body">
						<div class="form-check form-switch mb-3">
							<input class="form-check-input" type="checkbox" id="status_id" name="status_id" value="1" {{ old('status_id', $data->status_id ?? 0) == 1 ? 'checked' : '' }}>
							<label class="form-check-label" for="status_id">
								<strong>Active Campaign</strong>
								<div class="text-muted small">Enable this message for sending</div>
							</label>
						</div>
						<div class="form-check form-switch">
							<input class="form-check-input" type="checkbox" id="show_unsubscribe" name="show_unsubscribe" value="1" {{ old('show_unsubscribe', $data->show_unsubscribe ?? 1) == 1 ? 'checked' : '' }}>
							<label class="form-check-label" for="show_unsubscribe">
								<strong>Show Unsubscribe Link</strong>
								<div class="text-muted small">Include unsubscribe option in emails</div>
							</label>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="card">
					<div class="card-header">
						<h6 class="card-title mb-0">Tracking Options</h6>
					</div>
					<div class="card-body">
						<div class="form-check form-switch mb-3">
							<input class="form-check-input" type="checkbox" id="enable_open_tracking" name="enable_open_tracking" value="1" {{ old('enable_open_tracking', $data->enable_open_tracking ?? 1) == 1 ? 'checked' : '' }}>
							<label class="form-check-label" for="enable_open_tracking">
								<strong>Enable Open Tracking</strong>
								<div class="text-muted small">Track when emails are opened</div>
							</label>
						</div>
						<div class="form-check form-switch">
							<input class="form-check-input" type="checkbox" id="enable_click_tracking" name="enable_click_tracking" value="1" {{ old('enable_click_tracking', $data->enable_click_tracking ?? 1) == 1 ? 'checked' : '' }}>
							<label class="form-check-label" for="enable_click_tracking">
								<strong>Enable Click Tracking</strong>
								<div class="text-muted small">Track clicks on email links</div>
							</label>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="pt-4">
			<button type="submit" class="btn btn-primary me-sm-3 me-1">Send</button>
			<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('message-list') }}'">Cancel</button>
		</div>
	</form>
</div>
@endsection
