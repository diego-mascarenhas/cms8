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

{{-- JavaScript original comentado --}}
{{--
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
--}}

{{-- JavaScript nuevo para modal --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
	const serviceModal = document.getElementById('serviceModal');
	const serviceForm = document.getElementById('serviceForm');
	const servicesList = document.getElementById('services-list');
	const noServicesMessage = document.getElementById('no-services-message');
	const duplicateWarning = document.getElementById('duplicate-warning');
	
	let currentServices = [];
	let editingServiceId = null;
	
	// Load existing services
	loadExistingServices();
	
	// Handle fare selection change for units
	document.getElementById('fare_select').addEventListener('change', function() {
		const fareId = this.value;
		const unitSelect = document.getElementById('unit_select');
		
		if (!fareId) {
			unitSelect.innerHTML = '<option value="">Primero selecciona un servicio</option>';
			return;
		}
		
		// Show loading state
		unitSelect.innerHTML = '<option value="">Cargando unidades...</option>';
		unitSelect.disabled = true;
		
		// Fetch units for the selected fare
		fetch(`/project/fare-units?fare_id=${fareId}`, {
			method: 'GET',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
		.then(response => response.json())
		.then(data => {
			unitSelect.innerHTML = '<option value="">Seleccionar unidad</option>';
			
			if (data.units && data.units.length > 0) {
				data.units.forEach(unit => {
					const option = document.createElement('option');
					option.value = unit.type;
					option.textContent = unit.label;
					unitSelect.appendChild(option);
				});
			} else {
				unitSelect.innerHTML = '<option value="">No hay unidades disponibles</option>';
			}
		})
		.catch(error => {
			console.error('Error loading units:', error);
			unitSelect.innerHTML = '<option value="">Error al cargar unidades</option>';
		})
		.finally(() => {
			unitSelect.disabled = false;
		});
	});
	
	// Handle form submission
	serviceForm.addEventListener('submit', function(e) {
		e.preventDefault();
		
		const formData = new FormData(serviceForm);
		const serviceData = {
			service_id: formData.get('service_id'),
			project_id: formData.get('project_id'),
			fare_id: formData.get('fare_id'),
			source_language_code: formData.get('source_language_code'),
			target_language_code: formData.get('target_language_code'),
			quantity: formData.get('quantity'),
			unit: formData.get('unit')
		};
		
		// Check for duplicates
		if (isDuplicateService(serviceData)) {
			duplicateWarning.classList.remove('d-none');
			return;
		}
		
		duplicateWarning.classList.add('d-none');
		
		// Determine if we're editing or adding
		if (editingServiceId) {
			updateService(serviceData);
		} else {
			addService(serviceData);
		}
	});
	
	// Handle modal show event
	serviceModal.addEventListener('show.bs.modal', function(event) {
		const button = event.relatedTarget;
		const action = button?.getAttribute('data-action');
		
		if (action === 'edit') {
			const serviceId = button.getAttribute('data-service-id');
			editService(serviceId);
		} else {
			// Reset form for new service
			resetForm();
		}
	});
	
	// Handle modal hide event
	serviceModal.addEventListener('hide.bs.modal', function() {
		resetForm();
	});
	
	function loadExistingServices() {
		const projectId = document.getElementById('project-id').value;
		
		fetch(`/project/${projectId}/services`, {
			method: 'GET',
			headers: {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
		.then(response => response.json())
		.then(data => {
			if (data.services && data.services.length > 0) {
				currentServices = data.services;
				renderServices();
			}
		})
		.catch(error => {
			console.error('Error loading existing services:', error);
		});
	}
	
	function addService(serviceData) {
		const button = document.getElementById('saveServiceBtn');
		const originalText = button.innerHTML;
		
		button.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Guardando...';
		button.disabled = true;
		
		fetch(`/project/${serviceData.project_id}/service`, {
			method: 'POST',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
			},
			body: JSON.stringify(serviceData)
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				currentServices.push(data.service);
				renderServices();
				
				// Close modal
				const modal = bootstrap.Modal.getInstance(serviceModal);
				modal.hide();
				
				// Show success message
				showAlert('Servicio agregado exitosamente', 'success');
			} else {
				showAlert(data.message || 'Error al agregar servicio', 'error');
			}
		})
		.catch(error => {
			console.error('Error adding service:', error);
			showAlert('Error al agregar servicio', 'error');
		})
		.finally(() => {
			button.innerHTML = originalText;
			button.disabled = false;
		});
	}
	
	function updateService(serviceData) {
		const button = document.getElementById('saveServiceBtn');
		const originalText = button.innerHTML;
		
		button.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Actualizando...';
		button.disabled = true;
		
		fetch(`/project/${serviceData.project_id}/service/${serviceData.service_id}`, {
			method: 'PUT',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
			},
			body: JSON.stringify(serviceData)
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				// Update the service in current services
				const index = currentServices.findIndex(s => s.id == serviceData.service_id);
				if (index !== -1) {
					currentServices[index] = data.service;
				}
				renderServices();
				
				// Close modal
				const modal = bootstrap.Modal.getInstance(serviceModal);
				modal.hide();
				
				// Show success message
				showAlert('Servicio actualizado exitosamente', 'success');
			} else {
				showAlert(data.message || 'Error al actualizar servicio', 'error');
			}
		})
		.catch(error => {
			console.error('Error updating service:', error);
			showAlert('Error al actualizar servicio', 'error');
		})
		.finally(() => {
			button.innerHTML = originalText;
			button.disabled = false;
		});
	}
	
	function editService(serviceId) {
		const service = currentServices.find(s => s.id == serviceId);
		if (!service) return;
		
		editingServiceId = serviceId;
		
		// Update modal title
		document.getElementById('serviceModalTitle').textContent = 'Editar servicio';
		document.getElementById('saveServiceText').textContent = 'Actualizar servicio';
		
		// Fill form with service data
		document.getElementById('service-id').value = service.id;
		document.getElementById('source_language').value = service.source_language_code;
		document.getElementById('target_language').value = service.target_language_code;
		document.getElementById('fare_select').value = service.fare_id;
		document.getElementById('quantity').value = service.quantity;
		
		// Load units for the selected fare
		const fareSelect = document.getElementById('fare_select');
		fareSelect.dispatchEvent(new Event('change'));
		
		// Set unit after units are loaded
		setTimeout(() => {
			document.getElementById('unit_select').value = service.unit;
		}, 500);
	}
	
	function deleteService(serviceId) {
		if (!confirm('¿Estás seguro de que deseas eliminar este servicio?')) {
			return;
		}
		
		const projectId = document.getElementById('project-id').value;
		
		fetch(`/project/${projectId}/service/${serviceId}`, {
			method: 'DELETE',
			headers: {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
			}
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				currentServices = currentServices.filter(s => s.id != serviceId);
				renderServices();
				showAlert('Servicio eliminado exitosamente', 'success');
			} else {
				showAlert(data.message || 'Error al eliminar servicio', 'error');
			}
		})
		.catch(error => {
			console.error('Error deleting service:', error);
			showAlert('Error al eliminar servicio', 'error');
		});
	}
	
	function renderServices() {
		if (currentServices.length === 0) {
			noServicesMessage.classList.remove('d-none');
			servicesList.innerHTML = '';
			servicesList.appendChild(noServicesMessage);
			return;
		}
		
		noServicesMessage.classList.add('d-none');
		
		let html = '<div class="table-responsive">';
		html += '<table class="table table-hover">';
		html += '<thead>';
		html += '<tr>';
		html += '<th>Servicio</th>';
		html += '<th>Idiomas</th>';
		html += '<th>Cantidad</th>';
		html += '<th>Unidad</th>';
		html += '<th>Acciones</th>';
		html += '</tr>';
		html += '</thead>';
		html += '<tbody>';
		
		currentServices.forEach(service => {
			html += '<tr>';
			html += `<td><strong>${service.fare_name}</strong></td>`;
			html += `<td><span class="badge bg-primary me-1">${service.source_language_name}</span> → <span class="badge bg-success">${service.target_language_name}</span></td>`;
			html += `<td>${service.quantity}</td>`;
			html += `<td>${service.unit}</td>`;
			html += '<td>';
			html += `<button type="button" class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#serviceModal" data-action="edit" data-service-id="${service.id}">`;
			html += '<i class="ti ti-edit"></i>';
			html += '</button>';
			html += `<button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteService(${service.id})">`;
			html += '<i class="ti ti-trash"></i>';
			html += '</button>';
			html += '</td>';
			html += '</tr>';
		});
		
		html += '</tbody>';
		html += '</table>';
		html += '</div>';
		
		servicesList.innerHTML = html;
	}
	
	function isDuplicateService(serviceData) {
		return currentServices.some(service => 
			service.fare_id == serviceData.fare_id && 
			service.source_language_code === serviceData.source_language_code &&
			service.target_language_code === serviceData.target_language_code &&
			service.id != serviceData.service_id // Exclude current service when editing
		);
	}
	
	function resetForm() {
		serviceForm.reset();
		editingServiceId = null;
		document.getElementById('service-id').value = '';
		document.getElementById('serviceModalTitle').textContent = 'Agregar servicio';
		document.getElementById('saveServiceText').textContent = 'Agregar servicio';
		document.getElementById('unit_select').innerHTML = '<option value="">Primero selecciona un servicio</option>';
		duplicateWarning.classList.add('d-none');
	}
	
	function showAlert(message, type) {
		const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
		const alertHtml = `
			<div class="alert ${alertClass} alert-dismissible fade show" role="alert">
				${message}
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
			</div>
		`;
		
		const alertContainer = document.querySelector('.card-body');
		alertContainer.insertAdjacentHTML('afterbegin', alertHtml);
		
		// Auto-dismiss after 5 seconds
		setTimeout(() => {
			const alert = document.querySelector('.alert');
			if (alert) {
				alert.remove();
			}
		}, 5000);
	}
	
	// Make deleteService available globally
	window.deleteService = deleteService;
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

<!-- Formulario de servicios - VERSIÓN ORIGINAL (COMENTADA) -->
{{--
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
--}}

<!-- Formulario de servicios - VERSIÓN CON MODAL -->
<div class="card mb-4">
	<div class="card-header d-flex justify-content-between align-items-center">
		<h5 class="mb-0">Servicios vinculados</h5>
		<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#serviceModal">
			<i class="ti ti-plus me-1"></i>Agregar servicio
		</button>
	</div>
	<div class="card-body">
		<!-- Lista de servicios agregados -->
		<div id="services-list">
			<div class="text-center text-muted py-4" id="no-services-message">
				<i class="ti ti-folder-open ti-lg mb-2"></i>
				<p class="mb-0">No hay servicios agregados</p>
				<small>Haz clic en "Agregar servicio" para comenzar</small>
			</div>
		</div>
		
		<div class="pt-4">
			<div class="d-flex gap-3">
				<a href="{{ route('project.show', $project->id) }}" class="btn btn-label-secondary">
					<i class="ti ti-arrow-left me-1"></i>Volver al proyecto
				</a>
			</div>
		</div>
	</div>
</div>

<!-- Modal para agregar/editar servicio -->
<div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="serviceModalTitle">Agregar servicio</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form id="serviceForm">
				<div class="modal-body">
					<input type="hidden" id="service-id" name="service_id">
					<input type="hidden" id="project-id" name="project_id" value="{{ $project->id }}">
					
					<div class="row g-3">
						<div class="col-md-6">
							<x-variant-language-select 
								name="source_language_code" 
								id="source_language" 
								label="Idioma origen (*)" 
								:required="true"
								placeholder="Seleccionar idioma origen"
							/>
						</div>
						<div class="col-md-6">
							<x-variant-language-select 
								name="target_language_code" 
								id="target_language" 
								label="Idioma destino (*)" 
								:required="true"
								placeholder="Seleccionar idioma destino"
							/>
						</div>
						<div class="col-md-8">
							<label class="form-label">Tipo de servicio (*)</label>
							<select name="fare_id" id="fare_select" class="form-select" required>
								<option value="">Seleccionar servicio</option>
								@php
									$faresByType = \App\Models\Fare::with('type')
										->where(function($query) {
											$query->whereNull('team_id');
											if (auth()->check() && auth()->user()->currentTeam) {
												$query->orWhere('team_id', auth()->user()->currentTeam->id);
											}
										})
										->orderBy('name')
										->get()
										->groupBy(function($fare) {
											return $fare->type ? $fare->type->name : 'Sin categoría';
										});
								@endphp
								
								@foreach($faresByType as $typeName => $fareList)
									<optgroup label="{{ $typeName }}">
										@foreach($fareList as $fare)
											<option value="{{ $fare->id }}">{{ $fare->name }}</option>
										@endforeach
									</optgroup>
								@endforeach
							</select>
						</div>
						<div class="col-md-4">
							<label class="form-label">Cantidad (*)</label>
							<input type="number" name="quantity" id="quantity" class="form-control" 
								   value="1" min="1" step="1" required>
						</div>
						<div class="col-md-6">
							<label class="form-label">Unidad (*)</label>
							<select name="unit" id="unit_select" class="form-select" required>
								<option value="">Primero selecciona un servicio</option>
							</select>
						</div>
					</div>
					
					<div class="mt-3">
						<div class="alert alert-warning d-none" id="duplicate-warning">
							<i class="ti ti-alert-triangle me-2"></i>
							Este servicio ya está agregado con la misma combinación de idiomas.
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary" id="saveServiceBtn">
						<i class="ti ti-check me-1"></i>
						<span id="saveServiceText">Agregar servicio</span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

@endsection 