@extends('layouts/layoutMaster')

@section('title', __('Projects'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/form-layouts.js')}}"></script>
@endsection

@section('content')
	<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Projects') }}/</span> {{ __('Select Collaborators') }}</h4>
        <p class="text-muted">{{ __('Select collaborators to send project notifications') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
		<div class="d-flex align-content-center flex-wrap gap-3">
			<a href="{{ route('project.show', $project->id) }}" class="btn btn-label-secondary waves-effect waves-light">
				<i class="ti ti-arrow-left me-1"></i>Volver al proyecto
			</a>
		</div>
    </div>
</div>

	<form action="{{ route('project.send-notifications', $project->id) }}" method="POST" id="collaborator-selection-form">
		@csrf
		<input type="hidden" name="message_template" id="message_template" value="">

		    <!-- Filters Section -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="mb-3">Filtros</h5>
            <div class="row g-3">
                <div class="col">
                    <x-variant-language-select name="idioma-origen" id="idioma-origen" label="" :required="false"
                        placeholder="{{ __('Idioma origen') }}" />
                </div>
                <div class="col">
                    <x-variant-language-select name="idioma-destino" id="idioma-destino" label="" :required="false"
                        placeholder="{{ __('Idioma destino') }}" />
                </div>
                <div class="col">
                    <x-fare-select name="servicio" id="servicio" label="" :required="false"
                        placeholder="{{ __('Servicio') }}" />
                </div>
                <div class="col">
                    <select class="form-select" id="days">
                        <option value="" selected>{{ __('Días') }}</option>
                        <option value="5">5 días</option>
                        <option value="10">10 días</option>
                        <option value="15">15 días</option>
                        <option value="30">30 días</option>
                    </select>
                </div>
                <div class="col">
                    <select class="form-select" id="delivery-date">
                        <option value="" selected>{{ __('Fecha entrega') }}</option>
                        <option value="today" data-days="1">Hoy</option>
                        <option value="1_week" data-days="7">En 1 semana</option>
                        <option value="15_days" data-days="15">En 15 días</option>
                        <option value="1_month" data-days="30">En 1 mes</option>
                        <option value="3_months" data-days="90">En 3 meses</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

		    <!-- Collaborators Cards -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row" id="collaborators-container">
                @include('project.partials.collaborator-cards', ['collaborators' => $collaborators])
            </div>
        </div>
    </div>

		<!-- Message Templates -->
		<div class="row">
			<div class="col-md-6">
				<div class="card mb-4">
					<div class="card-body">
						<h6 class="card-title">Mensaje predefinido español</h6>
						<textarea class="form-control mb-3" rows="10" id="spanish-template" readonly>Hola, {nombre}:

	Esperamos que estés muy bien. Te contactamos desde bbo porque tenemos un nuevo proyecto. Hay que hacer {servicio}, de un {nombre_proyecto}, de {idioma_source} a {idioma_target}. La fecha de entrega ideal es {fecha_entrega_materiales}.

	¿Puedes confirmarnos tu tarifa por {unidad} y cuándo lo podrías tener?

	Dinos con cualquier duda.

	¡Gracias!</textarea>
					</div>
				</div>
			</div>

			<div class="col-md-6">
				<div class="card mb-4">
					<div class="card-body">
						<h6 class="card-title">Mensaje predefinido inglés</h6>
						<textarea class="form-control mb-3" rows="10" id="english-template" readonly>Hi, {nombre}:

	We hope you're doing well. We're writing to you from bbo because we have a new project. We need {servicio}, for {nombre_proyecto}, from {idioma_source} to {idioma_target}. The ideal delivery date would be {fecha_entrega_materiales}.

	Can you confirm your rate per {unidad} and when you could deliver?

	Let us know if you have any questions.

	Thanks!</textarea>
					</div>
				</div>
			</div>
		</div>

		<!-- Submit Button -->
		<div class="d-flex justify-content-center mb-4">
			<button type="submit" class="btn btn-primary btn-lg px-5" id="send-messages-btn">
				<i class="ti ti-send me-2"></i>Enviar mensajes
			</button>
		</div>
	</form>

	<script>
		document.addEventListener('DOMContentLoaded', function () {
			// Los componentes x-variant-language-select ya inicializan Select2 para idioma-origen e idioma-destino
			// No necesitamos inicializarlos aquí

			$('#servicio').select2({
				allowClear: true,
				placeholder: $('#servicio').find('option[value=""]').text() || 'Seleccionar...',
				width: '100%'
			});

			// Initialize days and delivery-date selects (copied from collaborator index)
			$('#days').select2({
				allowClear: true,
				placeholder: 'Seleccionar...',
				width: '100%'
			});

			$('#delivery-date').select2({
				allowClear: true,
				placeholder: 'Seleccionar...',
				width: '100%'
			});

			// La función formatLanguage ya está definida globalmente por el componente x-variant-language-select

			// Validation function for days vs delivery date (copied exactly from collaborator index)
			function validateDeliveryOptions() {
				var selectedDays = parseInt($('#days').val()) || 0;
				var deliverySelect = $('#delivery-date');
				
				// Reset all options to enabled and remove disabled text
				deliverySelect.find('option').prop('disabled', false).each(function() {
					var originalText = $(this).data('original-text') || $(this).text();
					$(this).data('original-text', originalText);
					$(this).text(originalText);
				});
				
				if (selectedDays > 0) {
					deliverySelect.find('option').each(function() {
						var optionDays = parseInt($(this).data('days'));
						if (!isNaN(optionDays) && optionDays < selectedDays) {
							$(this).prop('disabled', true);
							var originalText = $(this).data('original-text') || $(this).text();
							$(this).text(originalText + ' (insuficiente)');
							
							// If currently selected option becomes invalid, reset
							if ($(this).val() === deliverySelect.val()) {
								deliverySelect.val('');
							}
						}
					});
				}
			}

			// Filter functionality via AJAX (same approach as collaborator index)
			function applyFilters() {
				const sourceLanguage = $('#idioma-origen').val();
				const targetLanguage = $('#idioma-destino').val();
				const servicio = $('#servicio').val();
				const days = $('#days').val();
				const deliveryDate = $('#delivery-date').val();

				console.log('Applying filters via AJAX:', {
					sourceLanguage,
					targetLanguage,
					servicio,
					days,
					deliveryDate
				});

				// Check if any language or service filter is applied
				const hasLanguageFilter = sourceLanguage || targetLanguage;
				const hasServiceFilter = servicio;

				// If no filters applied, show empty state immediately
				if (!hasLanguageFilter && !hasServiceFilter) {
					$('#collaborators-container').html('<div class="col-12"><div class="alert alert-info"><i class="ti ti-info-circle me-2"></i>Selecciona una combinación de idiomas o un servicio para ver colaboradores disponibles.</div></div>');
					updateSelectedCount();
					return;
				}

				// Show loading state
				$('#collaborators-container').html('<div class="col-12 text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');

				// Make AJAX request to filter collaborators
				$.ajax({
					url: '{{ route("project.filter-collaborators", $project->id) }}',
					type: 'POST',
					data: {
						_token: '{{ csrf_token() }}',
						source_language: sourceLanguage,
						target_language: targetLanguage,
						servicio: servicio,
						days: days,
						delivery_date: deliveryDate
					},
					success: function(response) {
						$('#collaborators-container').html(response.html);
						updateSelectedCount();
						console.log('Filter successful, found', response.count, 'collaborators');
					},
					error: function(xhr, status, error) {
						console.error('Filter error:', error);
						$('#collaborators-container').html('<div class="col-12"><div class="alert alert-danger"><i class="ti ti-alert-circle me-2"></i>Error al filtrar colaboradores. Por favor, intenta de nuevo.</div></div>');
					}
				});
			}

			// Table filters (copied from collaborator index)
			$('#idioma-origen, #idioma-destino, #servicio, #days, #delivery-date').on('change', function () {
				// Validate delivery options when days change
				if (this.id === 'days') {
					validateDeliveryOptions();
				}
				
				console.log('Filter changed:', this.id, 'Value:', $(this).val());
				applyFilters();
			});

			// Initialize validation on page load
			validateDeliveryOptions();

			// Update selected count
			function updateSelectedCount() {
				const checkedCount = $('.collaborator-checkbox:checked').length;
				console.log(checkedCount + ' seleccionados');
			}

			// Checkbox change handler (using event delegation for dynamically loaded content)
			$(document).on('change', '.collaborator-checkbox', function () {
				const card = $(this).closest('.collaborator-card');
				const cardElement = card.find('.card');

				if ($(this).is(':checked')) {
					card.addClass('selected');
					cardElement.addClass('border-primary');
				} else {
					card.removeClass('selected');
					cardElement.removeClass('border-primary');
				}

				updateSelectedCount();
			});

			// Form submission handler
			$('#collaborator-selection-form').on('submit', function(e) {
				e.preventDefault();
				
				// Check if any collaborators are selected
				const selectedCollaborators = $('.collaborator-checkbox:checked');
				if (selectedCollaborators.length === 0) {
					alert('Por favor, selecciona al menos un colaborador');
					return false;
				}
				
				// Use Spanish template as default
				const messageTemplate = $('#spanish-template').val();
				$('#message_template').val(messageTemplate);
				
				// Log data being sent for debugging
				console.log('Sending form with:', {
					collaborators: selectedCollaborators.length,
					template: messageTemplate.substring(0, 50) + '...'
				});
				
				// Submit the form
				this.submit();
			});

			// Initialize count
			updateSelectedCount();
		});
	</script>

@endsection