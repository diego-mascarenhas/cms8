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
        @can('project.show')
        <a href="{{ route('project.show', $project->id) }}" class="btn btn-primary waves-effect waves-light"><i class="ti ti-eye me-1"></i>{{ __('View') }} {{ __('Project') }}</a>
        @endcan
        @can('project.edit')
        <a href="{{ route('project.edit', $project->id) }}" class="btn btn-success waves-effect waves-light"><i class="ti ti-edit me-1"></i>{{ __('Edit') }} {{ __('Project') }}</a>
        @endcan
    </div>
</div>

	<form action="{{ route('project.send-notifications', $project->id) }}" method="POST" id="collaborator-selection-form">
		@csrf

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
                    <select class="form-select" id="dias">
                        <option value="" selected>{{ __('Días') }}</option>
                        <option value="5">5 días</option>
                        <option value="10">10 días</option>
                        <option value="15">15 días</option>
                        <option value="30">30 días</option>
                    </select>
                </div>
                <div class="col">
                    <select class="form-select" id="fecha-entrega">
                        <option value="" selected>{{ __('Fecha entrega') }}</option>
                        <option value="today">Hoy</option>
                        <option value="week">Esta semana</option>
                        <option value="month">Este mes</option>
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
			// Initialize Select2 for filters (matching the collaborator index style)
			$('#idioma-origen, #idioma-destino, #servicio, #dias, #fecha-entrega').select2({
				allowClear: true,
				placeholder: 'Seleccionar...'
			});

			// Filter functionality via AJAX (same approach as collaborator index)
			function applyFilters() {
				const sourceLanguage = $('#idioma-origen').val();
				const targetLanguage = $('#idioma-destino').val();
				const servicio = $('#servicio').val();
				const dias = $('#dias').val();
				const fechaEntrega = $('#fecha-entrega').val();

				console.log('Applying filters via AJAX:', {
					sourceLanguage,
					targetLanguage,
					servicio,
					dias,
					fechaEntrega
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
						dias: dias,
						fecha_entrega: fechaEntrega
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

			// Auto-apply filters when changed
			$('#idioma-origen, #idioma-destino, #servicio, #dias, #fecha-entrega').on('change', function() {
				console.log('Filter changed:', this.id, 'Value:', $(this).val());
				applyFilters();
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

				if ($(this).is(':checked')) {
					card.addClass('selected');
					cardElement.addClass('border-primary');
				} else {
					card.removeClass('selected');
					cardElement.removeClass('border-primary');
				}

				updateSelectedCount();
			});

			// Initialize count
			updateSelectedCount();
		});
	</script>

@endsection