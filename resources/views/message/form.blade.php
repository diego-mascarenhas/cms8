@extends('layouts/layoutMaster')

@section('title', __('Messages'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/katex.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
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

// Convert display unit to stored hours before submit (idempotent for bfcache / validation re-renders).
document.addEventListener('DOMContentLoaded', function ()
{
    var storeForm = document.getElementById('message-store-form');
    if (! storeForm)
    {
        return;
    }

    storeForm.addEventListener('submit', function ()
    {
        var input = document.getElementById('min_hours_between_emails');
        var unitEl = document.getElementById('time_unit');
        if (! input || ! unitEl)
        {
            return;
        }

        storeForm.querySelectorAll('input[name="min_hours_between_emails"][data-min-hours-submit-synth="1"]').forEach(function (n)
        {
            n.remove();
        });
        input.setAttribute('name', 'min_hours_between_emails');

        var unit = unitEl.value;
        var value = parseFloat(input.value) || 0;
        var hours = value;
        if (unit === 'days')
        {
            hours = value * 24;
        } else if (unit === 'weeks')
        {
            hours = value * 24 * 7;
        }

        var hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'min_hours_between_emails';
        hiddenInput.value = String(Math.round(hours));
        hiddenInput.setAttribute('data-min-hours-submit-synth', '1');

        input.removeAttribute('name');
        storeForm.appendChild(hiddenInput);
    });
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

<form id="message-store-form" action="{{ route('message.store') }}" method="POST">
	@csrf
	<input type="hidden" name="id" value="{{ $data->id ?? '' }}">

	@php
		$useLegacyTemplatePicker = $data->useLegacyTemplatePicker ?? false;
		$removeMailTemplate = $removeMailTemplate ?? false;
		$showEmailTemplatePreview = ! $useLegacyTemplatePicker && ! $removeMailTemplate && isset($data->template, $data->emailTemplatePreviewHtml, $data->templateGrapesEditorUrl) && $data->template && (int) ($data->type_id ?? 0) === 1;

		$currentTypeIdForLock = (int) old('type_id', $data->type_id ?? 0);
		$effectiveTemplateIdForLock = old('template_id', $removeMailTemplate ? '' : ($data->template_id ?? ''));
		$hasEffectiveTemplateForLock = $effectiveTemplateIdForLock !== null
			&& $effectiveTemplateIdForLock !== ''
			&& (string) $effectiveTemplateIdForLock !== '0';
		$messageFormTypeIdDisabled = (isset($data->hasDeliveries) && $data->hasDeliveries)
			|| ($currentTypeIdForLock === 1 && $hasEffectiveTemplateForLock);

		$whatsAppTypeId = 2;
		foreach ($data->types ?? [] as $typeRow)
		{
			if (is_array($typeRow) && isset($typeRow['name']) && stripos((string) $typeRow['name'], 'whatsapp') !== false)
			{
				$whatsAppTypeId = (int) ($typeRow['id'] ?? 2);
				break;
			}
		}

		$templateSelectDisabled = (isset($data->hasDeliveries) && $data->hasDeliveries)
			|| ($currentTypeIdForLock === $whatsAppTypeId);

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

	<div id="message-form-template-id-slot"></div>

	<div class="card mb-4">
		<h5 class="card-header">{{ __('Messages') }}</h5>
		<div class="card-body">
		<div class="row g-3">
			<div class="col-md-6">
				<x-input-general id="name" label="{{ __('Subject') }} (*)" value="{{ old('name', $data->name?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-select
					id="type_id"
					label="{{ __('Canal') }} (*)"
					:options="$data->types"
					value="{{ old('type_id', $data->type_id ?? '') }}"
					:required="false"
					:allowClear="false"
					:disabled="$messageFormTypeIdDisabled"
				/>
				@if(isset($data->hasDeliveries) && $data->hasDeliveries)
					<div class="form-text text-warning mt-1">
						<i class="ti ti-alert-triangle me-1"></i>No se puede cambiar el canal porque el mensaje ya tiene entregas creadas.
					</div>
				@endif
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
					:placeholder="__('app.message_form_contact_status_all')"
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
			<div class="col-md-6" id="message-form-template-field-wrapper">
				<x-input-select
					id="template_id"
					label="{{ __('Plantilla') }}"
					:options="$data->templates ?? []"
					value="{{ old('template_id', $removeMailTemplate ? '' : ($data->template_id ?? '')) }}"
					:placeholder="__('app.message_form_template_none')"
					:disabled="$templateSelectDisabled"
				/>
				@if ($currentTypeIdForLock === $whatsAppTypeId && ! (isset($data->hasDeliveries) && $data->hasDeliveries))
					<div class="form-text text-muted mt-1">
						{{ __('Los mensajes por WhatsApp no usan plantilla de correo; solo texto alternativo.') }}
					</div>
				@endif
			</div>
		</div>
		</div>
	</div>

	<div id="message-email-template-preview-mount">
	@if ($showEmailTemplatePreview)
		@php
			$rawTemplateHtmlForMailField = '';
			if (isset($data->template->gjs_data) && is_array($data->template->gjs_data))
			{
				$rawTemplateHtmlForMailField = (string) ($data->template->gjs_data['html'] ?? '');
			}
			$mailHtmlTextareaValue = old('template_html', $rawTemplateHtmlForMailField);
			$mailHtmlTextareaReadonly = isset($data->hasDeliveries) && $data->hasDeliveries;
		@endphp
		@include('message.partials.email-template-content-preview', [
			'previewHtml' => $data->emailTemplatePreviewHtml,
			'grapesEditorUrl' => $data->templateGrapesEditorUrl,
			'templateLabel' => $data->template->name,
			'messageId' => $data->id ?? null,
			'templateId' => $data->template->id,
			'templateHashedId' => $data->template->getHashedId(),
			'duplicateFormId' => 'message-email-template-duplicate-form',
			'duplicateModalId' => 'message-email-template-duplicate-modal',
			'removeTemplateUrl' => null,
			'useMailHtmlTextarea' => true,
			'mailHtmlTextareaValue' => $mailHtmlTextareaValue,
			'mailHtmlTextareaReadonly' => $mailHtmlTextareaReadonly,
		])
	@endif
	</div>

	@php
		$messageFormScheduleCollapseOpen = $errors->has('send_allowed_weekdays')
			|| $errors->has('send_window_start')
			|| $errors->has('send_window_end')
			|| $errors->has('min_hours_between_emails');
	@endphp
	<div class="card mb-4">
		<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
			<div class="d-flex align-items-center gap-2 flex-wrap min-w-0">
				<h5 class="mb-0">{{ __('Message sending schedule') }}</h5>
				@env('local')
					<span class="text-success flex-shrink-0" title="APP_ENV=local"><i class="ti ti-bug ti-sm"></i></span>
				@endenv
			</div>
			<button
				type="button"
				class="btn btn-sm btn-icon btn-label-secondary {{ $messageFormScheduleCollapseOpen ? '' : 'collapsed' }}"
				data-bs-toggle="collapse"
				data-bs-target="#message-form-schedule-collapse"
				aria-expanded="{{ $messageFormScheduleCollapseOpen ? 'true' : 'false' }}"
				aria-controls="message-form-schedule-collapse"
				title="{{ __('app.message_form_toggle_section') }}"
			>
				<i class="ti ti-chevron-down"></i>
			</button>
		</div>
		<div id="message-form-schedule-collapse" class="collapse {{ $messageFormScheduleCollapseOpen ? 'show' : '' }}">
		<div class="card-body">
			<div class="row g-3">
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
	</div>

	<div class="card mb-4">
		<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
			<div class="d-flex align-items-center gap-2 flex-wrap min-w-0">
				<h6 class="card-title mb-0">{{ __('Opciones generales del mensaje: enlace de baja y seguimiento') }}</h6>
				@env('local')
					<span class="text-success flex-shrink-0" title="APP_ENV=local"><i class="ti ti-bug ti-sm"></i></span>
				@endenv
			</div>
			<button
				type="button"
				class="btn btn-sm btn-icon btn-label-secondary collapsed"
				data-bs-toggle="collapse"
				data-bs-target="#message-form-options-collapse"
				aria-expanded="false"
				aria-controls="message-form-options-collapse"
				title="{{ __('app.message_form_toggle_section') }}"
			>
				<i class="ti ti-chevron-down"></i>
			</button>
		</div>
		<div id="message-form-options-collapse" class="collapse">
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
	</div>

	<div class="d-flex flex-wrap align-items-center gap-2 pt-2">
		<button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
		<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('message.index') }}'">{{ __('Cancel') }}</button>
	</div>
</form>

	<form
		id="message-email-template-duplicate-form"
		method="post"
		class="d-none"
		aria-hidden="true"
		action="{{ ($showEmailTemplatePreview && isset($data->template)) ? route('template.duplicate', $data->template->getHashedId()) : '#' }}"
	>
		@csrf
		<input type="hidden" name="return_url" value="{{ isset($data->id) ? route('message.edit', $data->id) : request()->fullUrl() }}">
	</form>
@push('scripts')
<script>
(function ()
{
    var mount = document.getElementById('message-email-template-preview-mount');
    var previewUrl = @json(route('message.template-email-preview'));
    var messageFormMessageId = @json(isset($data->id) ? (int) $data->id : null);
    var typeLockedByDeliveries = @json((bool) (isset($data->hasDeliveries) && $data->hasDeliveries));
    var messageFormTypeIdServerDisabled = @json((bool) (isset($data->hasDeliveries) && $data->hasDeliveries));
    var whatsAppTypeId = @json((int) $whatsAppTypeId);
    var templateLockDeliveries = @json((bool) (isset($data->hasDeliveries) && $data->hasDeliveries));

    if (! mount)
    {
        return;
    }

    var templateSlot = document.getElementById('message-form-template-id-slot');
    var duplicateForm = document.getElementById('message-email-template-duplicate-form');

    function restoreTemplateFieldUi()
    {
        if (templateSlot)
        {
            templateSlot.innerHTML = '';
        }
        var typeSelect = document.getElementById('type_id');
        if (typeSelect && ! messageFormTypeIdServerDisabled && ! typeLockedByDeliveries)
        {
            typeSelect.disabled = false;
            if (window.jQuery && window.jQuery.fn.select2)
            {
                window.jQuery(typeSelect).prop('disabled', false).trigger('change.select2');
            }
        }
        if (duplicateForm)
        {
            duplicateForm.setAttribute('action', '#');
        }
    }

    function clearDynamicPreview()
    {
        mount.innerHTML = '';
        mount.dataset.dynamicPreview = '0';
        delete mount.dataset.loadedTemplateId;
        if (window.humaMessageTemplateQuillInstance)
        {
            window.humaMessageTemplateQuillInstance = null;
        }
        restoreTemplateFieldUi();
    }

    function removeStaleEmailTestSendModalsOutsidePreviewMount()
    {
        var selector = messageFormMessageId
            ? '#email-test-send-modal-' + messageFormMessageId
            : '[id^="email-test-send-modal-draft-"]';

        document.querySelectorAll(selector).forEach(function (el)
        {
            if (mount.contains(el))
            {
                return;
            }

            if (typeof bootstrap !== 'undefined' && bootstrap.Modal)
            {
                var inst = bootstrap.Modal.getInstance(el);

                if (inst)
                {
                    inst.hide();
                    inst.dispose();
                }
            }

            el.remove();
        });
    }

    function loadEmailTemplatePreview(templateId)
    {
        if (mount.dataset.dynamicPreview === '1' && mount.dataset.loadedTemplateId === String(templateId))
        {
            return;
        }

        var params = new URLSearchParams({ template_id: String(templateId), return_url: window.location.href.split('#')[0] });
        if (messageFormMessageId)
        {
            params.set('message_id', String(messageFormMessageId));
        }
        var nameEl = document.getElementById('name');
        if (nameEl && nameEl.value && String(nameEl.value).trim())
        {
            params.set('context_name', String(nameEl.value).trim());
        }

        mount.dataset.dynamicPreview = 'loading';

        fetch(previewUrl + '?' + params.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (res)
            {
                if (! res.ok)
                {
                    throw new Error('bad status');
                }
                return res.json();
            })
            .then(function (data)
            {
                if (! data || typeof data.html !== 'string')
                {
                    throw new Error('bad payload');
                }
                removeStaleEmailTestSendModalsOutsidePreviewMount();
                mount.innerHTML = data.html;
                mount.dataset.dynamicPreview = '1';
                mount.dataset.loadedTemplateId = String(templateId);
                var frame = mount.querySelector('[data-email-template-preview-frame="1"]');
                if (frame && typeof data.preview_html === 'string')
                {
                    frame.srcdoc = data.preview_html;
                }
                if (duplicateForm && data.duplicate_action_url)
                {
                    duplicateForm.setAttribute('action', data.duplicate_action_url);
                }
                var typeSelect = document.getElementById('type_id');
                if (typeSelect && ! typeLockedByDeliveries)
                {
                    typeSelect.disabled = true;
                    if (window.jQuery && window.jQuery.fn.select2)
                    {
                        window.jQuery(typeSelect).prop('disabled', true).trigger('change.select2');
                    }
                }
                if (window.humaBindEmailTestSendModals)
                {
                    window.humaBindEmailTestSendModals();
                }
                if (window.humaInitMessageTemplateHtmlQuill)
                {
                    window.humaInitMessageTemplateHtmlQuill(mount);
                }
            })
            .catch(function ()
            {
                if (mount.dataset.dynamicPreview === 'loading')
                {
                    mount.innerHTML = '';
                    mount.dataset.dynamicPreview = '0';
                    delete mount.dataset.loadedTemplateId;
                    restoreTemplateFieldUi();
                }
            });
    }

    window.jQuery(function ($)
    {
        var $tpl = $('#template_id');
        var $type = $('#type_id');
        if (! $tpl.length)
        {
            return;
        }

        function syncTemplateSelectForChannel()
        {
            if (templateLockDeliveries)
            {
                return;
            }

            var tplEl = document.getElementById('template_id');
            if (! tplEl)
            {
                return;
            }

            var typeId = parseInt($type.val(), 10);
            var lock = (typeId === whatsAppTypeId);

            if (lock)
            {
                if (($tpl.val() || '').toString().trim() !== '')
                {
                    $tpl.val(null);
                }
                tplEl.disabled = true;
                if (window.jQuery && window.jQuery.fn.select2)
                {
                    window.jQuery(tplEl).prop('disabled', true).trigger('change.select2');
                }
                clearDynamicPreview();

                return;
            }

            tplEl.disabled = false;
            if (window.jQuery && window.jQuery.fn.select2)
            {
                window.jQuery(tplEl).prop('disabled', false).trigger('change.select2');
            }
        }

        function evaluate()
        {
            syncTemplateSelectForChannel();

            var typeId = parseInt($type.val(), 10);
            var tid = ($tpl.val() || '').toString().trim();
            if (typeId !== 1 || ! tid)
            {
                clearDynamicPreview();
                return;
            }
            loadEmailTemplatePreview(parseInt(tid, 10));
        }

        $tpl.on('change select2:select', evaluate);
        $type.on('change select2:select', evaluate);
        window.setTimeout(evaluate, 0);
    });
})();
</script>
<script>
(function ()
{
    window.humaMessageTemplateQuillInstance = null;

    window.humaSyncMessageTemplateHtmlQuill = function ()
    {
        if (! window.humaMessageTemplateQuillInstance)
        {
            return;
        }
        var ta = document.getElementById('message-template-html-body');
        if (! ta)
        {
            return;
        }
        ta.value = window.humaMessageTemplateQuillInstance.root.innerHTML;
    };

    /**
     * @param {ParentNode|Document|null} root
     */
    window.humaInitMessageTemplateHtmlQuill = function (root)
    {
        root = root || document;
        if (typeof Quill === 'undefined')
        {
            return;
        }
        var ta = root.querySelector('#message-template-html-body');
        var mountEl = root.querySelector('#message-template-html-quill-editor');
        if (! ta || ! mountEl)
        {
            return;
        }
        if (mountEl.dataset.quillBound === '1')
        {
            return;
        }
        mountEl.dataset.quillBound = '1';
        window.humaMessageTemplateQuillInstance = null;

        var readonly = ta.hasAttribute('readonly');
        var toolbar = readonly
            ? false
            : [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                [{ align: [] }],
                ['link'],
                [{ color: [] }, { background: [] }],
                ['blockquote', 'code-block'],
                ['clean'],
            ];

        var quill = new Quill(mountEl, {
            theme: 'snow',
            modules: {
                toolbar: toolbar,
            },
            bounds: mountEl,
        });

        var jsonEl = root.querySelector('#message-template-html-initial-json');
        var initialHtml = '';
        if (jsonEl && jsonEl.textContent)
        {
            try
            {
                initialHtml = JSON.parse(jsonEl.textContent);
            }
            catch (e)
            {
                initialHtml = '';
            }
        }
        if (typeof initialHtml !== 'string')
        {
            initialHtml = '';
        }
        if (initialHtml.trim() === '' && (ta.value || '').trim() !== '')
        {
            initialHtml = ta.value;
        }

        initialHtml = initialHtml.trim();
        if (initialHtml !== '' && initialHtml !== '<p><br></p>' && initialHtml !== '<p></p>')
        {
            if (quill.clipboard && typeof quill.clipboard.dangerouslyPasteHTML === 'function')
            {
                quill.setContents([], 'silent');
                quill.clipboard.dangerouslyPasteHTML(0, initialHtml);
            }
            else
            {
                quill.root.innerHTML = initialHtml;
            }
        }

        if (readonly)
        {
            quill.enable(false);
        }

        window.humaMessageTemplateQuillInstance = quill;

        quill.on('text-change', function ()
        {
            if (! readonly)
            {
                ta.value = quill.root.innerHTML;
            }
        });

        ta.value = quill.root.innerHTML;
    };

    document.addEventListener('DOMContentLoaded', function ()
    {
        if (window.humaInitMessageTemplateHtmlQuill)
        {
            window.humaInitMessageTemplateHtmlQuill(document);
        }

        var storeForm = document.getElementById('message-store-form');
        if (storeForm)
        {
            storeForm.addEventListener('submit', function ()
            {
                if (window.humaSyncMessageTemplateHtmlQuill)
                {
                    window.humaSyncMessageTemplateHtmlQuill();
                }
            });
        }
    });
})();
</script>
@endpush
@endsection
