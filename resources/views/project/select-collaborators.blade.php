@extends('layouts/layoutMaster')

@section('title', 'Añadir nuevo proyecto - Mensaje a colaboradoras')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
@endsection

@section('vendor-script')
	<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('content')
	<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
		<div class="d-flex flex-column justify-content-center">
			<h4 class="mb-1 mt-3">Añadir nuevo proyecto - Mensaje a colaboradoras</h4>
			<p class="text-muted">Proyecto: <strong>{{ $project->real_name ?? $project->name }}</strong></p>
		</div>
		<div class="d-flex align-content-center flex-wrap gap-3">
			<a href="{{ route('project.show', $project->id) }}" class="btn btn-label-secondary waves-effect waves-light">
				<i class="ti ti-arrow-left me-1"></i>Ir al proyecto
			</a>
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
                @forelse($collaborators as $index => $collaborator)
                    @php
                        // Build language combinations string for filtering
                        $languageCombinations = [];
                        foreach($collaborator->languageVariants as $variant) {
                            $languageCombinations[] = $variant->source_language_code . '-' . $variant->target_language_code;
                        }
                        $languageString = implode(',', $languageCombinations);
                        
                        // Build services string for filtering
                        $serviceNames = [];
                        foreach($collaborator->fares as $fare) {
                            $serviceNames[] = strtolower($fare->name);
                        }
                        $servicesString = implode(',', $serviceNames);
                        
                        // Get the valoration for display
                        $valorationIcon = 'ti-star-filled text-warning';
                        $valorationText = 'Top';
                        if ($collaborator->valoration) {
                            switch($collaborator->valoration->name) {
                                case 'Lista negra':
                                    $valorationIcon = 'ti-x text-danger';
                                    $valorationText = 'Lista negra';
                                    break;
                                case 'Validada':
                                    $valorationIcon = 'ti-check text-success';
                                    $valorationText = 'Validada';
                                    break;
                                case 'En espera':
                                    $valorationIcon = 'ti-eye text-warning';
                                    $valorationText = 'Ojo';
                                    break;
                                case 'Interesante':
                                    $valorationIcon = 'ti-clock text-info';
                                    $valorationText = 'Interesante';
                                    break;
                            }
                        }
                        
                        // Get primary language combination for display
                        $primaryLanguage = '';
                        if ($collaborator->languageVariants->count() > 0) {
                            $firstVariant = $collaborator->languageVariants->first();
                            $sourceLang = $firstVariant->sourceLanguage ? $firstVariant->sourceLanguage->name : $firstVariant->source_language_code;
                            $targetLang = $firstVariant->targetLanguage ? $firstVariant->targetLanguage->name : $firstVariant->target_language_code;
                            $primaryLanguage = $sourceLang . ' → ' . $targetLang;
                        }
                    @endphp
                    
                    <div class="col-md-4 mb-3 collaborator-card" 
                         data-languages="{{ $languageString }}" 
                         data-services="{{ $servicesString }}">
                        <div class="card border">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-md me-3">
                                            <img src="{{asset('assets/img/avatars/' . (($index % 16) + 1) . '.png')}}" 
                                                 alt="{{ $collaborator->name }}" class="rounded-circle">
                                        </div>
                                        <div>
                                            <h6 class="mb-0">{{ $collaborator->name }}</h6>
                                            <small class="text-muted">{{ $primaryLanguage }}</small>
                                            <div class="d-flex align-items-center mt-1">
                                                <i class="ti {{ $valorationIcon }} ti-xs me-1"></i>
                                                <small class="text-muted">{{ $valorationText }}</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input collaborator-checkbox" type="checkbox"
                                               name="collaborator_ids[]" value="{{ $collaborator->id }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="ti ti-info-circle me-2"></i>
                            No hay colaboradores disponibles. Asegúrate de que los colaboradores tengan idiomas y servicios configurados.
                        </div>
                    </div>
                @endforelse
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

			// Filter functionality
			function applyFilters() {
				const idiomaOrigen = $('#idioma-origen').val();
				const idiomaDestino = $('#idioma-destino').val();
				const servicio = $('#servicio').val();
				const dias = $('#dias').val();
				const fechaEntrega = $('#fecha-entrega').val();

				$('.collaborator-card').each(function () {
					let show = true;

					// Apply language filters
					if (idiomaOrigen || idiomaDestino) {
						const cardLanguages = $(this).data('languages') || '';
						const languages = cardLanguages.split('-');

						if (idiomaOrigen && !languages.includes(idiomaOrigen)) {
							show = false;
						}
						if (idiomaDestino && !languages.includes(idiomaDestino)) {
							show = false;
						}
					}

					// Apply service filter
					if (servicio) {
						const cardServices = $(this).data('services') || '';
						if (!cardServices.includes(servicio)) {
							show = false;
						}
					}

					// Apply days filter (could be used for availability logic)
					if (dias) {
						// TODO: Implement days-based filtering logic
						// This would require availability data from the collaborators
					}

					// Apply delivery date filter
					if (fechaEntrega) {
						// TODO: Implement delivery date filtering logic
						// This would filter based on collaborator availability for the date range
					}

					$(this).toggle(show);
				});

				updateSelectedCount();
			}

			// Auto-apply filters when changed
			$('#idioma-origen, #idioma-destino, #servicio, #dias, #fecha-entrega').on('change', applyFilters);

			// Update selected count
			function updateSelectedCount() {
				const checkedCount = $('.collaborator-checkbox:checked').length;
				console.log(checkedCount + ' seleccionados');
			}

			// Checkbox change handler
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