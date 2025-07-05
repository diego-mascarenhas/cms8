@extends('layouts/layoutMaster')

@section('title', 'Agregar servicios al proyecto')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
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

<script>
	// Servicios vinculados - Funcionalidad
	let serviceIndex = 1;

	// Funciones auxiliares
	function loadUnits(fareId, unitSelect) {
		if (!fareId) {
			unitSelect.innerHTML = '<option value="">Seleccionar unidad</option>';
			return;
		}

		unitSelect.innerHTML = '<option value="">Cargando unidades...</option>';

		fetch('{{ route("project.get-fare-units") }}?fare_id=' + fareId, {
			method: 'GET',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
				'Accept': 'application/json'
			},
			credentials: 'same-origin'
		})
			.then(response => {
				if (!response.ok) {
					throw new Error(`HTTP error! status: ${response.status}`);
				}
				return response.json();
			})
			.then(data => {
				let options = '<option value="">Seleccionar unidad</option>';
				
				if (data.error) {
					options += '<option value="">Error: ' + data.error + '</option>';
				} else if (data.units && Array.isArray(data.units) && data.units.length > 0) {
					data.units.forEach(function(unit, index) {
						// Seleccionar automáticamente la primera unidad
						const selected = index === 0 ? 'selected' : '';
						options += `<option value="${unit.type}" ${selected}>${unit.label}</option>`;
					});
				} else {
					options += '<option value="">No hay unidades disponibles</option>';
				}
				
				unitSelect.innerHTML = options;
			})
			.catch(error => {
				console.error('Error cargando unidades:', error);
				unitSelect.innerHTML = '<option value="">Error cargando unidades</option>';
			});
	}

	function initializeSelect2(index) {
		if (typeof $ === 'undefined') return;
		
		// Inicializar Select2 en los elementos recién agregados
		setTimeout(() => {
			const fareSelect = $(`#fare_${index}`);
			const sourceSelect = $(`#source_language_${index}`);
			const targetSelect = $(`#target_language_${index}`);
			
			if (fareSelect.length && !fareSelect.hasClass('select2-hidden-accessible')) {
				fareSelect.select2({ width: '100%' });
			}
			
			if (sourceSelect.length && !sourceSelect.hasClass('select2-hidden-accessible')) {
				sourceSelect.select2({ width: '100%' });
			}
			
			if (targetSelect.length && !targetSelect.hasClass('select2-hidden-accessible')) {
				targetSelect.select2({ width: '100%' });
			}
		}, 100);
	}

	// Inicializar cuando el DOM esté listo
	document.addEventListener('DOMContentLoaded', function() {
		// Agregar nuevo servicio
		const addServiceBtn = document.getElementById('add-service');
		if (addServiceBtn) {
			addServiceBtn.addEventListener('click', function() {
				fetch('{{ route("project.get-service-template") }}?index=' + serviceIndex)
					.then(response => response.text())
					.then(template => {
						const container = document.getElementById('services-container');
						if (container) {
							container.insertAdjacentHTML('beforeend', template);
							initializeSelect2(serviceIndex);
							serviceIndex++;
						}
					})
					.catch(error => {
						console.error('Error agregando servicio:', error);
						alert('Error agregando servicio. Por favor intente de nuevo.');
					});
			});
		}

		// Eliminar servicio
		document.addEventListener('click', function(e) {
			if (e.target.closest('.remove-service')) {
				const serviceRows = document.querySelectorAll('.service-row');
				if (serviceRows.length > 1) {
					const serviceRow = e.target.closest('.service-row');
					serviceRow.remove();
				} else {
					alert('Al menos un servicio es requerido');
				}
			}
		});

		// Manejar cambio de tarifa para actualizar unidades
		document.addEventListener('change', function(e) {
			if (e.target.id && e.target.id.startsWith('fare_')) {
				const fareId = e.target.value;
				const serviceRow = e.target.closest('.service-row');
				const index = serviceRow.dataset.index;
				const unitSelect = serviceRow.querySelector(`select[id="unit_${index}"]`);
				
				if (unitSelect) {
					loadUnits(fareId, unitSelect);
				}
			}
		});
	});
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">Proyectos / {{ $project->name }} /</span> Agregar servicios</h4>
		<p class="text-muted">Agrega los servicios vinculados al proyecto</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3">
		<a href="{{ route('project.show', $project->id) }}" class="btn btn-label-secondary waves-effect waves-light">
			<i class="ti ti-arrow-left me-1"></i>Volver al proyecto
		</a>
	</div>
</div>

<!-- Información del proyecto -->
<div class="card mb-4">
	<div class="card-body">
		<div class="row">
			<div class="col-md-6">
				<h6 class="text-muted">Proyecto</h6>
				<p class="mb-2"><strong>{{ $project->name }}</strong></p>
				@if($project->real_name)
					<p class="mb-2"><small class="text-muted">Nombre real: {{ $project->real_name }}</small></p>
				@endif
			</div>
			<div class="col-md-6">
				<h6 class="text-muted">Cliente</h6>
				<p class="mb-2">{{ $project->client->name ?? 'N/A' }}</p>
				<h6 class="text-muted">Responsable</h6>
				<p class="mb-2">{{ $project->responsible->name ?? 'N/A' }}</p>
			</div>
		</div>
	</div>
</div>

<!-- Formulario de servicios -->
<div class="card mb-4">
	<h5 class="card-header">Servicios vinculados</h5>
	<form class="card-body" action="{{ route('project.store-services', $project->id) }}" method="POST">
		@csrf
		
		<div class="row g-4">
			<div class="col-12">
				<div id="services-container">
					@include('project.partials.service-row', ['index' => 0, 'projectFare' => null])
				</div>
				
				<button type="button" id="add-service" class="btn btn-outline-primary btn-sm">
					<i class="ti ti-plus me-1"></i>{{ __('Add service') }}
				</button>
			</div>
		</div>
		
		<div class="pt-4">
			<div class="d-flex gap-3">
				<button type="submit" class="btn btn-primary px-5">{{ __('Save services') }}</button>
				<a href="{{ route('project.show', $project->id) }}" class="btn btn-label-secondary">{{ __('Skip for now') }}</a>
			</div>
		</div>
	</form>
</div>

@endsection 