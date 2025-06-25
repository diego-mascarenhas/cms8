@extends('layouts/layoutMaster')

@section('title', 'Añadir nuevo proyecto - Mensaje a colaboradoras')

@section('vendor-style')
	<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
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
				<h6 class="text-muted mb-3">Filtro de búsqueda (con los datos anteriores)</h6>
				<div class="row g-3">
					<div class="col-md-2">
						<select class="form-select" id="source_language" name="source_language">
							<option value="">Idioma origen</option>
							@foreach($languages as $language)
								<option value="{{ $language->code }}">{{ $language->name }}</option>
							@endforeach
						</select>
					</div>

					<div class="col-md-2">
						<select class="form-select" id="target_language" name="target_language">
							<option value="">Idioma final</option>
							@foreach($languages as $language)
								<option value="{{ $language->code }}">{{ $language->name }}</option>
							@endforeach
						</select>
					</div>

					<div class="col-md-2">
						<select class="form-select" id="servicio" name="servicio">
							<option value="">Servicio</option>
							@foreach($fares as $fare)
								<option value="{{ $fare->id }}">{{ $fare->name }}</option>
							@endforeach
						</select>
					</div>

					<div class="col-md-2">
						<select class="form-select" id="days" name="days">
							<option value="">Días</option>
							<option value="1">1 día</option>
							<option value="2">2 días</option>
							<option value="3">3 días</option>
							<option value="7">1 semana</option>
							<option value="14">2 semanas</option>
							<option value="30">1 mes</option>
						</select>
					</div>

					<div class="col-md-2">
						<input type="date" class="form-control" id="delivery_date" name="delivery_date"
							placeholder="Fecha entrega"
							value="{{ $project->date_material ? \Carbon\Carbon::parse($project->date_material)->format('Y-m-d') : '' }}">
					</div>
				</div>
			</div>
		</div>

		<!-- Collaborators Cards -->
		<div class="card mb-4">
			<div class="card-body">
				<div class="row" id="collaborators-container">
					<!-- Sample collaborators - these would come from the database -->
					<div class="col-md-4 mb-3 collaborator-card" data-languages="es-en" data-services="traduccion">
						<div class="card border">
							<div class="card-body p-3">
								<div class="d-flex align-items-center justify-content-between">
									<div class="d-flex align-items-center">
										<div class="avatar avatar-md me-3">
											<img src="{{asset('assets/img/avatars/1.png')}}" alt="Richard Payne"
												class="rounded-circle">
										</div>
										<div>
											<h6 class="mb-0">Richard Payne</h6>
											<small class="text-muted">español</small>
										</div>
									</div>
									<div class="form-check">
										<input class="form-check-input collaborator-checkbox" type="checkbox"
											name="collaborator_ids[]" value="1">
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="col-md-4 mb-3 collaborator-card" data-languages="en-es" data-services="traduccion">
						<div class="card border">
							<div class="card-body p-3">
								<div class="d-flex align-items-center justify-content-between">
									<div class="d-flex align-items-center">
										<div class="avatar avatar-md me-3">
											<img src="{{asset('assets/img/avatars/2.png')}}" alt="Jennifer Summers"
												class="rounded-circle">
										</div>
										<div>
											<h6 class="mb-0">Jennifer Summers</h6>
											<small class="text-muted">inglés</small>
										</div>
									</div>
									<div class="form-check">
										<input class="form-check-input collaborator-checkbox" type="checkbox"
											name="collaborator_ids[]" value="2">
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="col-md-4 mb-3 collaborator-card selected" data-languages="en-es" data-services="traduccion">
						<div class="card border border-primary">
							<div class="card-body p-3">
								<div class="d-flex align-items-center justify-content-between">
									<div class="d-flex align-items-center">
										<div class="avatar avatar-md me-3">
											<img src="{{asset('assets/img/avatars/3.png')}}" alt="Jordan Stevenson"
												class="rounded-circle">
										</div>
										<div>
											<h6 class="mb-0">Jordan Stevenson</h6>
											<small class="text-muted">inglés</small>
										</div>
									</div>
									<div class="form-check">
										<input class="form-check-input collaborator-checkbox" type="checkbox"
											name="collaborator_ids[]" value="3" checked>
										<i class="ti ti-edit ms-2 text-primary"></i>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="col-md-4 mb-3 collaborator-card" data-languages="en-es" data-services="traduccion">
						<div class="card border">
							<div class="card-body p-3">
								<div class="d-flex align-items-center justify-content-between">
									<div class="d-flex align-items-center">
										<div class="avatar avatar-md me-3">
											<img src="{{asset('assets/img/avatars/4.png')}}" alt="María García"
												class="rounded-circle">
										</div>
										<div>
											<h6 class="mb-0">María García</h6>
											<small class="text-muted">inglés</small>
										</div>
									</div>
									<div class="form-check">
										<input class="form-check-input collaborator-checkbox" type="checkbox"
											name="collaborator_ids[]" value="4">
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="col-md-4 mb-3 collaborator-card" data-languages="en-es" data-services="traduccion">
						<div class="card border">
							<div class="card-body p-3">
								<div class="d-flex align-items-center justify-content-between">
									<div class="d-flex align-items-center">
										<div class="avatar avatar-md me-3">
											<img src="{{asset('assets/img/avatars/5.png')}}" alt="Otra persona"
												class="rounded-circle">
										</div>
										<div>
											<h6 class="mb-0">Otra persona</h6>
											<small class="text-muted">inglés</small>
											<div class="form-check mt-1">
												<input class="form-check-input collaborator-checkbox" type="checkbox"
													name="collaborator_ids[]" value="5">
												<i class="ti ti-edit ms-2 text-muted"></i>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="col-md-4 mb-3 collaborator-card" data-languages="es-en" data-services="traduccion">
						<div class="card border">
							<div class="card-body p-3">
								<div class="d-flex align-items-center justify-content-between">
									<div class="d-flex align-items-center">
										<div class="avatar avatar-md me-3">
											<img src="{{asset('assets/img/avatars/6.png')}}" alt="Otro más aquí"
												class="rounded-circle">
										</div>
										<div>
											<h6 class="mb-0">Otro más aquí</h6>
											<small class="text-muted">español</small>
										</div>
									</div>
									<div class="form-check">
										<input class="form-check-input collaborator-checkbox" type="checkbox"
											name="collaborator_ids[]" value="6">
									</div>
								</div>
							</div>
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
		document.addEventListener('DOMContentLoaded', function () {
			// Initialize Select2 for filters
			$('#source_language, #target_language, #servicio, #days').select2({
				allowClear: true,
				placeholder: 'Seleccionar...'
			});

			// Filter functionality
			function applyFilters() {
				const sourceLanguage = $('#source_language').val();
				const targetLanguage = $('#target_language').val();
				const servicio = $('#servicio').val();
				const days = $('#days').val();

				$('.collaborator-card').each(function () {
					let show = true;

					// Apply language filters
					if (sourceLanguage || targetLanguage) {
						const cardLanguages = $(this).data('languages') || '';
						const languages = cardLanguages.split('-');

						if (sourceLanguage && !languages.includes(sourceLanguage)) {
							show = false;
						}
						if (targetLanguage && !languages.includes(targetLanguage)) {
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

					$(this).toggle(show);
				});

				updateSelectedCount();
			}

			// Auto-apply filters when changed
			$('#source_language, #target_language, #servicio, #days, #delivery_date').on('change', applyFilters);

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