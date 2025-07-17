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
				<i class="ti ti-arrow-left me-1"></i>{{ __('Back to project') }}
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
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">{{ __('Filters') }}</h5>
                <button class="btn btn-outline-secondary" id="clear-filters" title="{{ __('Limpiar filtros') }}">
                    <i class="ti ti-refresh me-1"></i>{{ __('Limpiar filtros') }}
                </button>
            </div>
            <div class="row g-3">
                <div class="col">
                    <label for="source-language" class="form-label">{{ __('Idioma origen') }}</label>
                    <x-variant-language-select name="source-language" id="source-language" label="" :required="false"
                        placeholder="{{ __('Idioma origen') }}" value="{{ $selectedSourceLanguage }}" :show-base-languages="false" />
                </div>
                <div class="col">
                    <label for="target-language" class="form-label">{{ __('Idioma destino') }}</label>
                    <x-variant-language-select name="target-language" id="target-language" label="" :required="false"
                        placeholder="{{ __('Idioma destino') }}" value="{{ $selectedTargetLanguage }}" :show-base-languages="false" />
                </div>
                <div class="col">
                    <label for="service" class="form-label">{{ __('Servicio') }}</label>
                    <x-fare-select name="service" id="service" label="" :required="false"
                        placeholder="{{ __('Servicio') }}" :selected="$selectedService ? [$selectedService] : []" />
                </div>
                <div class="col">
                    <label for="days" class="form-label">{{ __('Días') }}</label>
                    <select class="form-select" id="days">
                        <option value="" selected>{{ __('Días') }}</option>
                        <option value="5">5 días</option>
                        <option value="10">10 días</option>
                        <option value="15">15 días</option>
                        <option value="30">30 días</option>
                    </select>
                </div>
                <div class="col">
                    <x-input-date id="delivery-date" label="{{ __('Fecha entrega') }}" value="{{ $project->date_end }}" />
                </div>
            </div>
        </div>
    </div>

		    <!-- Collaborators Cards -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row" id="collaborators-container">
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="ti ti-info-circle me-2"></i>{{ __('Selecciona una combinación de idiomas, un servicio, o criterios de tiempo para ver colaboradores disponibles.') }}
                    </div>
                </div>
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
		// Blade variables for auto-filtering
		@if($selectedSourceLanguage || $selectedTargetLanguage || $selectedService)
		var hasUrlParameters = true;
		var urlSourceLanguage = '{{ $selectedSourceLanguage }}';
		var urlTargetLanguage = '{{ $selectedTargetLanguage }}';
		var urlService = '{{ $selectedService }}';
		@else
		var hasUrlParameters = false;
		@endif

		document.addEventListener('DOMContentLoaded', function () {
			// Add event listener for delivery date changes
			$(document).on('change', '#delivery-date', function() {
				console.log('Delivery date changed:', $(this).val());
				// Only apply filters if there are other filters or if this is a manual change
				const sourceLanguage = $('#source-language').val();
				const targetLanguage = $('#target-language').val();
				const service = $('#service').val();
				const days = $('#days').val();

				// Only apply filters if there are other filters or if this is not the initial load
				if (sourceLanguage || targetLanguage || service || days) {
					applyFilters();
				}
			});

			// Filter functionality via AJAX (same approach as collaborator index)
			function applyFilters() {
				const sourceLanguage = $('#source-language').val();
				const targetLanguage = $('#target-language').val();
				const service = $('#service').val();
				const days = $('#days').val();
				const deliveryDate = $('#delivery-date').val();

				// Debug days value specifically
				console.log('Days value in applyFilters:', {
					raw: days,
					type: typeof days,
					length: days ? days.length : 'null/undefined',
					boolean: !!days
				});

				// Check if any filter is applied
				const hasLanguageFilter = sourceLanguage || targetLanguage;
				const hasServiceFilter = service;
				const hasTimeFilter = days || deliveryDate;

				console.log('Applying filters via AJAX:', {
					sourceLanguage,
					targetLanguage,
					service,
					days,
					deliveryDate,
					hasLanguageFilter,
					hasServiceFilter,
					hasTimeFilter
				});

				// Special logging for time filters
				if (hasTimeFilter) {
					console.log('Time filter details:', {
						days: days,
						deliveryDate: deliveryDate,
						daysText: days ? $('#days option:selected').text() : 'none',
						deliveryDateText: deliveryDate,
						hasTimeFilter: hasTimeFilter,
						daysOnly: !!days && !deliveryDate,
						deliveryOnly: !!deliveryDate && !days
					});
				}

				// Special test for days only filter
				if (days && !deliveryDate && !hasLanguageFilter && !hasServiceFilter) {
					console.log('DAYS ONLY FILTER DETECTED:', {
						daysValue: days,
						willProceed: true
					});
				}

				// If no meaningful filters applied, show empty state immediately
				// A delivery date alone is not enough to search - need either language filters, service, or days
				if (!hasLanguageFilter && !hasServiceFilter && !days) {
					$('#collaborators-container').html('<div class="col-12"><div class="alert alert-info"><i class="ti ti-info-circle me-2"></i>Selecciona una combinación de idiomas, un servicio, o criterios de tiempo para ver colaboradores disponibles.</div></div>');
					updateSelectedCount();
					return;
				}

				// Show loading state
				$('#collaborators-container').html('<div class="col-12 text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');

				// Prepare AJAX data
				const ajaxData = {
					_token: '{{ csrf_token() }}',
					source_language: sourceLanguage,
					target_language: targetLanguage,
					service: service,
					days: days,
					delivery_date: deliveryDate
				};

				console.log('AJAX data being sent:', ajaxData);

				// Make AJAX request to filter collaborators
				$.ajax({
					url: '{{ route("project.filter-collaborators", $project->id) }}',
					type: 'POST',
					data: ajaxData,
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
			$('#source-language, #target-language, #service, #days, #delivery-date').on('change', function () {
				console.log('Filter changed:', this.id, 'Value:', $(this).val());

				// Special logging for time filters
				if (this.id === 'days' || this.id === 'delivery-date') {
					console.log('Time filter changed:', {
						id: this.id,
						value: $(this).val(),
						element: this
					});
				}

				applyFilters();
			});

			// Clear filters functionality
			$('#clear-filters').on('click', function(e) {
				e.preventDefault();

				// Clear all filter values
				$('#source-language, #target-language, #service, #days, #delivery-date').val('').trigger('change');

				// Reset collaborators view to show initial state
				$('#collaborators-container').html('<div class="col-12"><div class="alert alert-info"><i class="ti ti-info-circle me-2"></i>Selecciona una combinación de idiomas, un servicio, o criterios de tiempo para ver colaboradores disponibles.</div></div>');

				// Update selected count
				updateSelectedCount();

				console.log('Filters cleared');
			});

			// Update selected count
			function updateSelectedCount() {
				const checkedCount = $('.collaborator-checkbox:checked').length;
				console.log(checkedCount + ' seleccionados');
			}

			// Checkbox change handler (using event delegation for dynamically loaded content)
			$(document).on('change', '.collaborator-checkbox', function () {
				const card = $(this).closest('.collaborator-card');
				const cardElement = card.find('.card');
				const collaboratorId = $(this).data('collaborator-id');

				if ($(this).is(':checked')) {
					card.addClass('selected');
					cardElement.addClass('border-primary');

					// Only show collapse if no service is selected (fares are shown inline when service is selected)
					const faresCollapse = $('#fares-' + collaboratorId);
					if (faresCollapse.length > 0) {
						faresCollapse.collapse('show');

						// Filter fares based on current filters after the collapse animation completes
						setTimeout(function() {
							filterCollaboratorFares(collaboratorId);
						}, 350); // Bootstrap collapse animation duration
					}
				} else {
					card.removeClass('selected');
					cardElement.removeClass('border-primary');

					// Hide collaborator fares collapse if it exists
					const faresCollapse = $('#fares-' + collaboratorId);
					if (faresCollapse.length > 0) {
						faresCollapse.collapse('hide');
					}
				}

				updateSelectedCount();
			});

			// Function to filter collaborator fares based on current filter selections
			function filterCollaboratorFares(collaboratorId) {
				const currentService = $('#service').val();
				const currentSourceLang = $('#source-language').val();
				const currentTargetLang = $('#target-language').val();

				// Get the fares container for this collaborator
				const faresContainer = $('#fares-' + collaboratorId);
				const fareRows = faresContainer.find('.fare-row');

				console.log('Filtering fares for collaborator', collaboratorId, {
					service: currentService,
					sourceLang: currentSourceLang,
					targetLang: currentTargetLang,
					totalFareRows: fareRows.length
				});

				let visibleCount = 0;

				fareRows.each(function() {
					const row = $(this);
					const fareServiceId = String(row.data('service-id'));
					const fareSourceLang = row.data('source-lang');
					const fareTargetLang = row.data('target-lang');

					console.log('Checking fare row:', {
						fareServiceId: fareServiceId,
						currentService: currentService,
						fareSourceLang: fareSourceLang,
						fareTargetLang: fareTargetLang,
						serviceMatch: !currentService || fareServiceId === currentService
					});

					let shouldShow = true;

					// Filter by service if selected (this is the most important filter)
					if (currentService && fareServiceId !== String(currentService)) {
						shouldShow = false;
						console.log('Hidden due to service mismatch:', fareServiceId, '!==', currentService);
					}

					// Filter by source language if selected
					if (shouldShow && currentSourceLang && fareSourceLang !== 'N/A' && fareSourceLang !== currentSourceLang) {
						shouldShow = false;
						console.log('Hidden due to source language mismatch:', fareSourceLang, '!==', currentSourceLang);
					}

					// Filter by target language if selected
					if (shouldShow && currentTargetLang && fareTargetLang !== 'N/A' && fareTargetLang !== currentTargetLang) {
						shouldShow = false;
						console.log('Hidden due to target language mismatch:', fareTargetLang, '!==', currentTargetLang);
					}

					if (shouldShow) {
						row.show();
						visibleCount++;
						console.log('Showing fare row for service:', fareServiceId);
					} else {
						row.hide();
					}
				});

				console.log('Visible fares count:', visibleCount, 'out of', fareRows.length);

				// Show/hide the "no matching fares" message
				const noFaresMessage = faresContainer.find('.no-matching-fares');
				if (visibleCount === 0 && fareRows.length > 0) {
					if (noFaresMessage.length === 0) {
						faresContainer.find('.card-body').append(
							'<p class="text-muted mb-0 no-matching-fares" style="font-size: 0.8rem;">' +
							'<i class="ti ti-info-circle me-1"></i>No hay tarifas para el servicio seleccionado' +
							'</p>'
						);
					} else {
						noFaresMessage.show();
					}
				} else {
					noFaresMessage.remove();
				}

				// Hide the entire fares section if no fares match and a service is selected
				if (visibleCount === 0 && currentService && fareRows.length > 0) {
					// Could optionally hide the entire fares section, but showing the message is better UX
				}
			}

			// Update fare filtering when filters change
			$(document).on('change', '#source-language, #target-language, #service', function() {
				console.log('Filter changed, re-filtering fares for all selected collaborators');
				// Re-filter fares for all selected collaborators
				$('.collaborator-checkbox:checked').each(function() {
					const collaboratorId = $(this).data('collaborator-id');
					console.log('Re-filtering fares for collaborator:', collaboratorId);
					filterCollaboratorFares(collaboratorId);
				});
			});

			// Also filter fares when new collaborators are loaded via AJAX
			$(document).ajaxComplete(function(event, xhr, settings) {
				// Check if this was a collaborator filter request
				if (settings.url && settings.url.includes('/filter-collaborators')) {
					console.log('Collaborators loaded via AJAX, applying fare filters to any selected collaborators');
					// Small delay to ensure DOM is updated
					setTimeout(function() {
						$('.collaborator-checkbox:checked').each(function() {
							const collaboratorId = $(this).data('collaborator-id');
							filterCollaboratorFares(collaboratorId);
						});
					}, 100);
				}
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

			// Auto-apply filters if URL parameters are present
			if (hasUrlParameters) {
				setTimeout(function() {
					console.log('Auto-applying filters from URL parameters:', {
						sourceLanguage: urlSourceLanguage,
						targetLanguage: urlTargetLanguage,
						service: urlService
					});

					// Trigger filter application
					applyFilters();
				}, 500); // Small delay to ensure Select2 components are fully initialized
			} else {
				console.log('No URL parameters found, showing initial state');
			}

			// Initialize count
			updateSelectedCount();

			// Log initial state
			console.log('Collaborator selection page initialized:', {
				hasUrlParameters: hasUrlParameters,
				initialCollaboratorsCount: {{ $collaborators->count() }},
				url: window.location.href
			});
		});
	</script>

@endsection
