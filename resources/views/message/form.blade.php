@extends('layouts/layoutMaster')

@section('title', __('Messages'))

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
// Delete message function with Sweet Alert
function deleteMessage(messageId) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "¿Deseas eliminar este mensaje?",
        icon: 'warning',
        showCancelButton: true,
        buttonsStyling: false,
        customClass: {
            confirmButton: 'btn btn-danger me-2',
            cancelButton: 'btn btn-label-secondary'
        },
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
    document.querySelectorAll('#message-time-presets .btn').forEach(btn => {
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
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Messages') }}/</span> {{ isset($data->id) ? __('Edit') : __('Create') }} News</h4>
        <p class="text-muted small mb-0">{{ __('Channel, template and audience.') }}</p>
    </div>
    @if(isset($data->id))
    <div class="d-flex align-content-center flex-wrap gap-3">
        <button type="button" class="btn btn-danger" onclick="deleteMessage({{ $data->id }})">
            <i class="ti ti-trash me-1"></i>{{ __('Delete') }}
        </button>
    </div>
    @endif
</div>

<div class="card mb-4">
	<h5 class="card-header">{{ __('Messages') }}</h5>
	<form class="card-body" action="{{ route('message.store') }}" method="POST">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">

		@php
			$useLegacyTemplatePicker = $data->useLegacyTemplatePicker ?? false;
			$showEmailTemplatePreview = ! $useLegacyTemplatePicker && isset($data->template, $data->emailTemplatePreviewHtml, $data->templateGrapesEditorUrl) && $data->template && (int) ($data->type_id ?? 0) === 1;
		@endphp

		<div class="row g-3">
			<div class="col-md-6">
				<x-input-general id="name" label="{{ __('Name') }} (*)" value="{{ old('name', $data->name?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-select id="type_id" label="{{ __('Canal') }} (*)" :options="$data->types" value="{{ old('type_id', $data->type_id ?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-module-categories-select
					id="category_id"
					label="Categoría"
					moduleKey="contacts"
					:selected="old('category_id', $data->category_id ?? '')"
					:allowEmpty="true"
					emptyText="Toda la base de datos"
					:disabled="isset($data->hasDeliveries) && $data->hasDeliveries"
				/>
				@if(isset($data->hasDeliveries) && $data->hasDeliveries)
					<div class="form-text text-warning mt-1">
						<i class="ti ti-alert-triangle me-1"></i>No se puede cambiar la categoría porque el mensaje ya tiene entregas creadas.
					</div>
				@endif
			</div>
			<div class="col-md-6">
				<x-input-select
					id="contact_status_id"
					label="{{ __('Estado del contacto') }}"
					:options="$data->contactStatuses ?? []"
					value="{{ old('contact_status_id', $data->contact_status_id ?? '') }}"
					:disabled="isset($data->hasDeliveries) && $data->hasDeliveries"
				/>
				@if(isset($data->hasDeliveries) && $data->hasDeliveries)
					<div class="form-text text-warning mt-1">
						<i class="ti ti-alert-triangle me-1"></i>No se puede cambiar el estado porque el mensaje ya tiene entregas creadas.
					</div>
				@endif
			</div>
			<div class="col-md-12">
				<x-input-textarea id="text" label="{{ __('Texto alternativo') }} (*)" value="{{ old('text', $data->text?? '') }}" />
				<div class="form-text mt-1">
					{{ __('Para WhatsApp o para clientes de correo sin HTML. Si usas plantilla, este texto sirve como fallback o versión corta.') }}
				</div>
			</div>
			@if ($showEmailTemplatePreview)
				<input type="hidden" name="template_id" value="{{ $data->template_id }}">
				<div class="col-12">
					@include('message.partials.email-template-content-preview', [
						'previewHtml' => $data->emailTemplatePreviewHtml,
						'grapesEditorUrl' => $data->templateGrapesEditorUrl,
						'templateLabel' => $data->template->name,
						'messageId' => $data->id ?? null,
					])
				</div>
			@else
				<div class="col-md-6">
					<x-input-select id="template_id" label="{{ __('Plantilla') }}" :options="$data->templates ?? []" value="{{ old('template_id', $data->template_id ?? '') }}" />
					<div class="form-text mt-1">
						¿No encuentras el template que buscas? <a href="{{ route('template.create') }}">Agregar nuevo template</a>
					</div>
				</div>
				@if (isset($data->id) && (int) ($data->type_id ?? 0) === 1 && $data->template_id && $data->template)
					<div class="col-md-6 d-flex flex-column flex-md-row align-items-md-end justify-content-md-end gap-2 mt-2 mt-md-0">
						<a href="{{ route('template.editor', $data->template->getHashedId()) }}" class="btn btn-primary waves-effect waves-light">
							<i class="ti ti-external-link me-1"></i>{{ __('Abrir editor visual') }}
						</a>
					</div>
				@endif
			@endif
			<div class="col-md-6">
				<div class="form-group mb-0">
					<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1" style="min-height: 2.25rem;">
						<label for="min_hours_between_emails" class="form-label mb-0">{{ __('Minimum Time Between Emails') }}</label>
					</div>
					<div class="input-group input-group-merge">
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
							<option value="hours" selected>{{ __('Hours') }}</option>
							<option value="days">{{ __('Days') }}</option>
							<option value="weeks">{{ __('Weeks') }}</option>
						</select>
					</div>
					<div class="form-text mt-1">
						{{ __('Time to wait before sending another email to the same contact') }}
					</div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="form-group mb-0">
					<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1" style="min-height: 2.25rem;">
						<span class="form-label mb-0">{{ __('Quick Presets') }}</span>
					</div>
					<div class="d-flex flex-nowrap w-100 rounded overflow-hidden border shadow-none" id="message-time-presets" role="group" aria-label="{{ __('Quick Presets') }}">
						<button type="button" class="btn btn-outline-secondary border-0 border-end rounded-0 flex-grow-1 text-nowrap px-2 py-2 shadow-none" onclick="setTimePreset(0, 'hours')">{{ __('Immediate') }}</button>
						<button type="button" class="btn btn-outline-secondary border-0 border-end rounded-0 flex-grow-1 text-nowrap px-2 py-2 shadow-none" onclick="setTimePreset(24, 'hours')">{{ __('1 Day') }}</button>
						<button type="button" class="btn btn-outline-secondary border-0 border-end rounded-0 flex-grow-1 text-nowrap px-2 py-2 shadow-none" onclick="setTimePreset(48, 'hours')">{{ __('2 Days') }}</button>
						<button type="button" class="btn btn-outline-secondary border-0 rounded-0 flex-grow-1 text-nowrap px-2 py-2 shadow-none" onclick="setTimePreset(1, 'weeks')">{{ __('1 Week') }}</button>
					</div>
					<div class="form-text mt-1">
						{{ __('Aplica un valor sugerido al tiempo mínimo entre correos.') }}
					</div>
				</div>
			</div>
		</div>

		<div class="row g-3 mt-3">
			<div class="col-12">
				<div class="card">
					<div class="card-header">
						<h6 class="card-title mb-0">{{ __('Opciones generales del mensaje: enlace de baja y seguimiento') }}</h6>
					</div>
					<div class="card-body">
						@if (isset($data->id))
							<input type="hidden" name="status_id" value="{{ (((int) old('status_id', (int) ($data->status_id ?? 0))) === 1) ? 1 : 0 }}">
						@endif
						<div class="row g-4 align-items-start">
							<div class="col-lg-6 align-self-start">
								<div class="form-check form-switch">
									<input class="form-check-input" type="checkbox" id="show_unsubscribe" name="show_unsubscribe" value="1" {{ old('show_unsubscribe', $data->show_unsubscribe ?? 1) == 1 ? 'checked' : '' }}>
									<label class="form-check-label" for="show_unsubscribe">
										<strong>{{ __('Mostrar enlace de baja') }}</strong>
										<div class="text-muted small">{{ __('Incluye la opción para darse de baja en los correos.') }}</div>
									</label>
								</div>
							</div>
							<div class="col-lg-6 align-self-start mt-4 mt-lg-0 pt-4 pt-lg-0">
								<div class="d-flex flex-column gap-4">
									<div class="form-check form-switch">
										<input class="form-check-input" type="checkbox" id="enable_open_tracking" name="enable_open_tracking" value="1" {{ old('enable_open_tracking', $data->enable_open_tracking ?? 1) == 1 ? 'checked' : '' }}>
										<label class="form-check-label" for="enable_open_tracking">
											<strong>{{ __('Habilitar seguimiento de aperturas') }}</strong>
											<div class="text-muted small">{{ __('Rastrear cuando se abren los correos.') }}</div>
										</label>
									</div>
									<div class="form-check form-switch mb-0">
										<input class="form-check-input" type="checkbox" id="enable_click_tracking" name="enable_click_tracking" value="1" {{ old('enable_click_tracking', $data->enable_click_tracking ?? 1) == 1 ? 'checked' : '' }}>
										<label class="form-check-label" for="enable_click_tracking">
											<strong>{{ __('Habilitar seguimiento de clics') }}</strong>
											<div class="text-muted small">{{ __('Rastrear clics en los enlaces del correo.') }}</div>
										</label>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="pt-4">
			<button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Save') }}</button>
			<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('message.index') }}'">{{ __('Cancel') }}</button>
		</div>
	</form>
</div>
@endsection
