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
document.addEventListener('DOMContentLoaded', function () {
    const timeUnitSelect = document.getElementById('time_unit');
    const minHoursInput = document.getElementById('min_hours_between_emails');
    if (!timeUnitSelect || !minHoursInput) {
        return;
    }

    if (!timeUnitSelect.dataset.prevUnit) {
        timeUnitSelect.dataset.prevUnit = timeUnitSelect.value;
    }

    timeUnitSelect.addEventListener('change', function () {
        const prevUnit = timeUnitSelect.dataset.prevUnit || 'days';
        const nextUnit = timeUnitSelect.value;
        const displayed = parseFloat(minHoursInput.value) || 0;

        let hoursNow = displayed;
        if (prevUnit === 'days') {
            hoursNow = displayed * 24;
        } else if (prevUnit === 'weeks') {
            hoursNow = displayed * 24 * 7;
        }

        if (nextUnit === 'hours') {
            minHoursInput.value = Math.round(hoursNow);
            minHoursInput.setAttribute('step', '1');
        } else if (nextUnit === 'days') {
            minHoursInput.value = Math.round((hoursNow / 24) * 100) / 100;
            minHoursInput.setAttribute('step', '0.5');
        } else {
            minHoursInput.value = Math.round((hoursNow / (24 * 7)) * 10) / 10;
            minHoursInput.setAttribute('step', '0.1');
        }

        timeUnitSelect.dataset.prevUnit = nextUnit;
    });
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

<form action="{{ route('message.store') }}" method="POST">
	@csrf
	<input type="hidden" name="id" value="{{ $data->id ?? '' }}">

	@php
		$useLegacyTemplatePicker = $data->useLegacyTemplatePicker ?? false;
		$showEmailTemplatePreview = ! $useLegacyTemplatePicker && isset($data->template, $data->emailTemplatePreviewHtml, $data->templateGrapesEditorUrl) && $data->template && (int) ($data->type_id ?? 0) === 1;

		$storedMinHoursBetweenEmails = (float) old('min_hours_between_emails', $data->min_hours_between_emails ?? 48);
		$initialTimeUnit = old('time_unit', 'days');
		if (! in_array($initialTimeUnit, ['hours', 'days', 'weeks'], true))
		{
			$initialTimeUnit = 'days';
		}

		$betweenEmailsInputStep = match ($initialTimeUnit)
		{
			'days' => '0.5',
			'weeks' => '0.1',
			default => '1',
		};
		$betweenEmailsDisplayValue = match ($initialTimeUnit)
		{
			'days' => round($storedMinHoursBetweenEmails / 24, 2),
			'weeks' => round($storedMinHoursBetweenEmails / (24 * 7), 2),
			default => fmod($storedMinHoursBetweenEmails, 1.0) === 0.0 ? (int) $storedMinHoursBetweenEmails : round($storedMinHoursBetweenEmails, 2),
		};

		$sendingScheduleWeekdayDefinitions = [
			1 => __('Monday_short'),
			2 => __('Tuesday_short'),
			3 => __('Wednesday_short'),
			4 => __('Thursday_short'),
			5 => __('Friday_short'),
			6 => __('Saturday_short'),
			7 => __('Sunday_short'),
		];

		$allowedSendWeekdays = old('send_allowed_weekdays');
		if (! is_array($allowedSendWeekdays))
		{
			$dbWeekdays = isset($data->send_allowed_weekdays) && $data->send_allowed_weekdays !== null
				? array_map('intval', (array) $data->send_allowed_weekdays)
				: null;
			$allowedSendWeekdays = $dbWeekdays ?? range(1, 7);
		} else {
			$allowedSendWeekdays = array_map('intval', $allowedSendWeekdays);
		}
		$allowedSendWeekdays = array_values(array_unique($allowedSendWeekdays));
		sort($allowedSendWeekdays);

		$sendWindowStartValue = old('send_window_start', $data->send_window_start ?? '');
		$sendWindowEndValue = old('send_window_end', $data->send_window_end ?? '');
	@endphp

	@if ($showEmailTemplatePreview)
		<input type="hidden" name="template_id" value="{{ $data->template_id }}">
	@endif

	<div class="card mb-4">
		<h5 class="card-header">{{ __('Messages') }}</h5>
		<div class="card-body">
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
			@unless ($showEmailTemplatePreview)
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
			@endunless
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
							step="{{ $betweenEmailsInputStep }}"
							value="{{ $betweenEmailsDisplayValue }}"
						>
						<select class="form-select" id="time_unit" name="time_unit" style="max-width: 120px;" data-prev-unit="{{ $initialTimeUnit }}">
							<option value="hours" @selected($initialTimeUnit === 'hours')>{{ __('Hours') }}</option>
							<option value="days" @selected($initialTimeUnit === 'days')>{{ __('Days') }}</option>
							<option value="weeks" @selected($initialTimeUnit === 'weeks')>{{ __('Weeks') }}</option>
						</select>
					</div>
					<div class="form-text mt-1">
						{{ __('Time to wait before sending another email to the same contact') }}
					</div>
				</div>
			</div>
			<div class="col-12">
				<div class="border rounded px-3 py-3 mb-1">
					<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-2">
						<div>
							<span class="form-label d-block mb-1">{{ __('Allowed sending weekdays') }}</span>
							<div class="form-text">{{ __('Recipients are only queued during checked days.') }}</div>
						</div>
					</div>
					<div class="d-flex flex-wrap column-gap-3 row-gap-2 mb-3">
						@foreach ($sendingScheduleWeekdayDefinitions as $isoWeekday => $shortLabel)
							<div class="form-check mb-0">
								<input
									class="form-check-input"
									type="checkbox"
									name="send_allowed_weekdays[]"
									id="send-allowed-{{ $isoWeekday }}"
									value="{{ $isoWeekday }}"
									{{ in_array((int) $isoWeekday, $allowedSendWeekdays, true) ? 'checked' : '' }}
								>
								<label class="form-check-label small" for="send-allowed-{{ $isoWeekday }}">{{ $shortLabel }}</label>
							</div>
						@endforeach
					</div>
					<div class="form-label">{{ __('Sending time window') }} <span class="text-muted fw-normal">({{ __('optional') }} — {{ config('app.timezone') }})</span></div>
					<div class="row g-3 align-items-end">
						<div class="col-auto">
							<label for="send_window_start" class="form-label small mb-1">{{ __('Sending window start') }}</label>
							<input
								type="time"
								step="300"
								class="form-control @error('send_window_start') is-invalid @enderror"
								id="send_window_start"
								name="send_window_start"
								value="{{ $sendWindowStartValue }}"
								autocomplete="off"
							>
							@error('send_window_start')
								<div class="invalid-feedback d-block">{{ $message }}</div>
							@enderror
						</div>
						<div class="col-auto">
							<label for="send_window_end" class="form-label small mb-1">{{ __('Sending window end') }}</label>
							<input
								type="time"
								step="300"
								class="form-control @error('send_window_end') is-invalid @enderror"
								id="send_window_end"
								name="send_window_end"
								value="{{ $sendWindowEndValue }}"
								autocomplete="off"
							>
							@error('send_window_end')
								<div class="invalid-feedback d-block">{{ $message }}</div>
							@enderror
						</div>
					</div>
					@error('send_allowed_weekdays')
						<div class="text-danger small mt-2">{{ $message }}</div>
					@enderror
					<div class="form-text mt-2 mb-0">
						{{ __('Leave both times empty for 24h delivery.') }} {{ __('The end time must be after the start time (same calendar day).') }}
					</div>
				</div>
			</div>
		</div>
		</div>
	</div>

	@if ($showEmailTemplatePreview)
		@include('message.partials.email-template-content-preview', [
			'previewHtml' => $data->emailTemplatePreviewHtml,
			'grapesEditorUrl' => $data->templateGrapesEditorUrl,
			'templateLabel' => $data->template->name,
			'messageId' => $data->id ?? null,
		])
	@endif

	<div class="card mb-4">
		<div class="card-header">
			<h6 class="card-title mb-0">{{ __('Opciones generales del mensaje: enlace de baja y seguimiento') }}</h6>
		</div>
		<div class="card-body">
			@if (isset($data->id))
				<input type="hidden" name="status_id" value="{{ (((int) old('status_id', (int) ($data->status_id ?? 0))) === 1) ? 1 : 0 }}">
			@endif
			<div class="row gy-4 gx-lg-4 align-items-start">
				<div class="col-12 col-lg-6 align-self-start">
					<div class="form-check form-switch">
						<input class="form-check-input" type="checkbox" id="show_unsubscribe" name="show_unsubscribe" value="1" {{ old('show_unsubscribe', $data->show_unsubscribe ?? 1) == 1 ? 'checked' : '' }}>
						<label class="form-check-label" for="show_unsubscribe">
							<strong>{{ __('Mostrar enlace de baja') }}</strong>
							<div class="text-muted small">{{ __('Incluye la opción para darse de baja en los correos.') }}</div>
						</label>
					</div>
				</div>
				<div class="col-12 col-lg-6 align-self-start">
					<div class="form-check form-switch">
						<input class="form-check-input" type="checkbox" id="enable_open_tracking" name="enable_open_tracking" value="1" {{ old('enable_open_tracking', $data->enable_open_tracking ?? 1) == 1 ? 'checked' : '' }}>
						<label class="form-check-label" for="enable_open_tracking">
							<strong>{{ __('Habilitar seguimiento de aperturas') }}</strong>
							<div class="text-muted small">{{ __('Rastrear cuando se abren los correos.') }}</div>
						</label>
					</div>
				</div>
				<div class="col-12 col-lg-6 offset-lg-6 align-self-start">
					<div class="form-check form-switch mb-lg-0">
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

	<div class="d-flex flex-wrap align-items-center gap-2 pt-2">
		<button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
		<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('message.index') }}'">{{ __('Cancel') }}</button>
	</div>
</form>
@endsection
