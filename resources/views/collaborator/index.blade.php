@extends('layouts/layoutMaster')

@section('title', __('Collaborators'))

@section('vendor-style')
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
	<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endsection

@section('vendor-script')
	<script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
	<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
	<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
	<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
	<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
	<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
@endsection

@section('page-script')
	<script src="{{ asset('assets/js/ui-toasts.js') }}"></script>
@endsection

<style>
	.fade-out {
		opacity: 0;
		transition: opacity 0.5s ease-out;
	}

	/* Hide native DataTables export buttons */
	.dt-buttons,
	.buttons-html5,
	.buttons-print,
	.btn-secondary {
		display: none !important;
	}

	/* Tooltip styling for better visibility */
	.tooltip {
		z-index: 9999 !important;
	}

	.tooltip-inner {
		max-width: 300px;
		background-color: #495057;
		color: white;
		text-align: left;
		font-size: 12px;
		line-height: 1.4;
	}

	/* Active filter styling */
	.filter-status.active-filter {
		transform: scale(1.1);
		box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), 0.25);
		transition: all 0.2s ease-in-out;
	}

	.filter-status {
		transition: all 0.2s ease-in-out;
		cursor: pointer;
	}

	.filter-status:hover {
		transform: scale(1.05);
	}

	/* Statistics section animations */
	#service-statistics {
		opacity: 0;
		transform: translateY(-20px);
		transition: all 0.3s ease-in-out;
	}

	#service-statistics.show {
		opacity: 1;
		transform: translateY(0);
	}

	/* Statistics cards hover effects */
	#service-statistics .avatar-initial {
		transition: all 0.2s ease-in-out;
	}

	#service-statistics .d-flex:hover .avatar-initial {
		transform: scale(1.1);
	}

	/* DataTable column centering */
	#collaborator-table .text-center {
		text-align: center !important;
	}

	/* Language combination styling */
	.language-combination {
		text-align: center !important;
	}

	/* Date input styling */
	.flatpickr-input {
		background-color: #fff !important;
		border: 1px solid #d9dee3 !important;
		border-radius: 0.375rem !important;
		padding: 0.4375rem 0.875rem !important;
		font-size: 0.9375rem !important;
		line-height: 1.53 !important;
		color: #697a8d !important;
	}

	.flatpickr-input:focus {
		border-color: #696cff !important;
		box-shadow: 0 0 0.25rem rgba(105, 108, 255, 0.1) !important;
	}

	/* Flatpickr calendar styling */
	.flatpickr-calendar {
		background: #fff !important;
		border: 1px solid #d9dee3 !important;
		border-radius: 0.375rem !important;
		box-shadow: 0 0.25rem 1.125rem rgba(75, 70, 92, 0.1) !important;
	}

	.flatpickr-day.selected {
		background: #696cff !important;
		border-color: #696cff !important;
	}

	.flatpickr-day:hover {
		background: #e7e7ff !important;
	}

	/* Responsive adjustments for smaller screens */
	@media (max-width: 768px) {
		#collaborator-table .min-desktop {
			display: none !important;
		}

		#collaborator-table .min-tablet {
			width: auto !important;
		}

		/* Adjust language combinations for mobile */
		.language-combination {
			font-size: 0.9em !important;
			word-break: break-word !important;
		}
	}

	@media (max-width: 576px) {
		#collaborator-table .min-phone {
			width: auto !important;
		}

		/* Make table more compact on very small screens */
		#collaborator-table td, #collaborator-table th {
			padding: 0.5rem 0.25rem !important;
		}
	}
</style>

@section('content')
	@if (session('success'))
		<div id="toast-container" class="toast-top-right">
			<div class="toast toast-success" aria-live="polite" style="display: block;">
				<div class="toast-message">{{ session('success') }}</div>
			</div>
		</div>

		<script>
			document.addEventListener('DOMContentLoaded', function () {
				var toastElement = document.getElementById('toast-container');
				var toast = new bootstrap.Toast(toastElement, {
					animation: true,
					delay: 1000,
					autohide: true
				});
				toast.show();
			});
		</script>
	@endif

	<!-- Stats Cards -->
	<div class="row mb-4">
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card shadow-sm">
				<div class="card-body">
					<div class="d-flex justify-content-between">
						<div>
							<h6 class="text-muted mb-0">{{ __('Por aceptar') }}</h6>
							<div class="d-flex align-items-center mt-1">
								<h3 class="mb-0 me-1">{{ number_format($dashboardStats['pendingAcceptance']) }}</h3>
							</div>
							<small class="text-muted">{{ __('Contactos sin usuario') }}</small>
						</div>
						<div class="avatar">
							<a href="#" class="avatar-initial rounded bg-label-warning filter-status" data-filter="pending-acceptance">
								<i class="ti ti-clock"></i>
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card shadow-sm">
				<div class="card-body">
					<div class="d-flex justify-content-between">
						<div>
							<h6 class="text-muted mb-0">{{ __('Colaboradoras') }}</h6>
							<div class="d-flex align-items-center mt-1">
								<h3 class="mb-0 me-1">{{ number_format($dashboardStats['totalCollaborators']) }}</h3>
							</div>
							<small class="text-muted">{{ __('Con rol colaborador') }}</small>
						</div>
						<div class="avatar">
							<a href="#" class="avatar-initial rounded bg-label-primary filter-status" data-filter="collaborators">
								<i class="ti ti-users"></i>
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card shadow-sm">
				<div class="card-body">
					<div class="d-flex justify-content-between">
						<div>
							<h6 class="text-muted mb-0">{{ __('Nuevos') }}</h6>
							<div class="d-flex align-items-center mt-1">
								<h3 class="mb-0 me-1">{{ number_format($dashboardStats['newThisWeek']) }}</h3>
							</div>
							<small class="text-muted">{{ __('Última semana') }}</small>
						</div>
						<div class="avatar">
							<a href="#" class="avatar-initial rounded bg-label-success filter-status" data-filter="new-this-week">
								<i class="ti ti-user-plus"></i>
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card shadow-sm">
				<div class="card-body">
					<div class="d-flex justify-content-between">
						<div>
							<h6 class="text-muted mb-0">{{ __('Sin actualizar') }}</h6>
							<div class="d-flex align-items-center mt-1">
								<h3 class="mb-0 me-1">{{ number_format($dashboardStats['notUpdatedSixMonths']) }}</h3>
							</div>
							<small class="text-muted">{{ __('Últimos 6 meses') }}</small>
						</div>
						<div class="avatar">
							<a href="#" class="avatar-initial rounded bg-label-danger filter-status" data-filter="not-updated-six-months">
								<i class="ti ti-user-check"></i>
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Add New Collaborator Button -->
	<div class="d-flex justify-content-start mb-3">
		@can('collaborator.create')
			<a href="{{ route('collaborator.create') }}"
				class="btn btn-primary d-flex align-items-center gap-1">
				<i class="ti ti-plus"></i>
				<span>{{ __('Añadir nuevo') }}</span>
			</a>
		@endcan
	</div>

	<div class="card shadow">
		<div class="card-body">
			<h5 class="mb-3">Filtros</h5>
			<div class="row g-3 mb-3">
				@if(auth()->user()->currentTeam->hasModule('language-variants'))
				<div class="col">
					<label for="source-language" class="form-label">{{ __('Idioma origen') }}</label>
					<x-variant-language-select name="source-language" id="source-language" label="" :required="false"
						placeholder="{{ __('Idioma origen') }}" :show-base-languages="true" />
				</div>
				<div class="col">
					<label for="target-language" class="form-label">{{ __('Idioma destino') }}</label>
					<x-variant-language-select name="target-language" id="target-language" label="" :required="false"
						placeholder="{{ __('Idioma destino') }}" :show-base-languages="true" />
				</div>
				@endif
				<div class="col">
					<label for="service" class="form-label">{{ __('Servicio') }}</label>
					<x-fare-select name="service" id="service" label="" :required="false"
						placeholder="{{ __('Servicio') }}" />
				</div>
				<div class="col">
					<label for="status" class="form-label">{{ __('Estado') }}</label>
					<select class="form-select" id="status">
						<option value="" selected>{{ __('Todos los estados') }}</option>
						<option value="1">✅ Revisado</option>
						<option value="2">❓ Para revisión</option>
						<option value="3">⚠️ Pendiente</option>
					</select>
				</div>
				<div class="col" style="display: none;">
					<label for="days" class="form-label">{{ __('Días') }}</label>
					<select class="form-select" id="days">
						<option value="" selected>{{ __('Días') }}</option>
						<option value="5">5 días</option>
						<option value="10">10 días</option>
						<option value="15">15 días</option>
						<option value="30">30 días</option>
					</select>
				</div>
				<div class="col" style="display: none;">
					<x-input-date id="delivery-date" label="{{ __('Fecha entrega') }}" value="" />
				</div>
			</div>
			<div class="row align-items-center mb-4">
				<div class="col-md-1">
					<select class="form-select" id="entries-length">
						<option value="25">25</option>
						<option value="50">50</option>
						<option value="100">100</option>
					</select>
				</div>
				<div class="col-md-6"></div>
				<div class="col-md-5 d-flex justify-content-end align-items-center gap-2">
					<input type="text" class="form-control w-auto me-2" id="search" placeholder="{{ __('Buscar') }}"
						style="width: 350px;">
					<div class="dropdown">
						<button class="btn btn-outline-primary dropdown-toggle me-2" type="button" id="exportDropdown"
							data-bs-toggle="dropdown" aria-expanded="false" style="height: 40px; min-width: 110px;">
							<i class="ti ti-download me-1"></i>
							<span style="white-space: nowrap;">{{ __('Exportar') }}</span>
						</button>
						<ul class="dropdown-menu" aria-labelledby="exportDropdown">
							<li><a class="dropdown-item" href="#" id="export-csv"><i
										class="ti ti-file-text me-2"></i>CSV</a></li>
							<li><a class="dropdown-item" href="#" id="export-pdf"><i
										class="ti ti-file-text me-2"></i>PDF</a></li>
						</ul>
					</div>
					<button class="btn btn-outline-secondary ms-2" id="clear-filters" style="height: 40px;" title="{{ __('Limpiar filtros') }}">
						<i class="ti ti-refresh"></i>
					</button>
				</div>
			</div>

			<hr>

			{{ $dataTable->table(['class' => 'table table-hover table-striped dt-responsive nowrap w-100']) }}

			<!-- Statistics Section -->
			<div id="service-statistics" class="mt-4" style="display: none;">
				<div class="card">
					<div class="card-header">
						<h5 class="mb-0">
							<i class="ti ti-chart-bar me-2"></i>
							<span id="statistics-service-name">Estadísticas del servicio</span>
						</h5>
						<small class="text-muted">
							<i class="ti ti-check text-success ti-xs me-1"></i>
							Calculadas únicamente sobre colaboradores con estado "Revisado"
						</small>
					</div>
					<div class="card-body">
						<div class="row">
							<div class="col-md-3 col-sm-6 mb-3">
								<div class="d-flex align-items-center">
									<div class="avatar avatar-sm me-3">
										<span class="avatar-initial rounded bg-label-primary">
											<i class="ti ti-calculator"></i>
										</span>
									</div>
									<div>
										<h6 class="mb-0" id="statistics-media">-</h6>
										<small class="text-muted">Media</small>
									</div>
								</div>
							</div>
							<div class="col-md-3 col-sm-6 mb-3">
								<div class="d-flex align-items-center">
									<div class="avatar avatar-sm me-3">
										<span class="avatar-initial rounded bg-label-success">
											<i class="ti ti-chart-line"></i>
										</span>
									</div>
									<div>
										<h6 class="mb-0" id="statistics-mediana">-</h6>
										<small class="text-muted">Mediana</small>
									</div>
								</div>
							</div>
							<div class="col-md-3 col-sm-6 mb-3">
								<div class="d-flex align-items-center">
									<div class="avatar avatar-sm me-3">
										<span class="avatar-initial rounded bg-label-warning">
											<i class="ti ti-chart-pie"></i>
										</span>
									</div>
									<div>
										<h6 class="mb-0" id="statistics-moda">-</h6>
										<small class="text-muted">Moda</small>
									</div>
								</div>
							</div>
							<div class="col-md-3 col-sm-6 mb-3">
								<div class="d-flex align-items-center">
									<div class="avatar avatar-sm me-3">
										<span class="avatar-initial rounded bg-label-info">
											<i class="ti ti-users"></i>
										</span>
									</div>
									<div>
										<h6 class="mb-0" id="statistics-count">-</h6>
										<small class="text-muted">Colaboradores</small>
									</div>
								</div>
							</div>
						</div>
						<div class="row mt-3">
							<div class="col-md-3 col-sm-6 mb-3">
								<div class="d-flex align-items-center">
									<div class="avatar avatar-sm me-3">
										<span class="avatar-initial rounded bg-label-danger">
											<i class="ti ti-arrow-down"></i>
										</span>
									</div>
									<div>
										<h6 class="mb-0" id="statistics-min">-</h6>
										<small class="text-muted">Mínimo</small>
									</div>
								</div>
							</div>
							<div class="col-md-3 col-sm-6 mb-3">
								<div class="d-flex align-items-center">
									<div class="avatar avatar-sm me-3">
										<span class="avatar-initial rounded bg-label-success">
											<i class="ti ti-arrow-up"></i>
										</span>
									</div>
									<div>
										<h6 class="mb-0" id="statistics-max">-</h6>
										<small class="text-muted">Máximo</small>
									</div>
								</div>
							</div>
							<div class="col-md-3 col-sm-6 mb-3">
								<div class="d-flex align-items-center">
									<div class="avatar avatar-sm me-3">
										<span class="avatar-initial rounded bg-label-secondary">
											<i class="ti ti-arrows-horizontal"></i>
										</span>
									</div>
									<div>
										<h6 class="mb-0" id="statistics-range">-</h6>
										<small class="text-muted">Rango</small>
									</div>
								</div>
							</div>
							<div class="col-md-3 col-sm-6 mb-3">
								<div class="d-flex align-items-center">
									<div class="avatar avatar-sm me-3">
										<span class="avatar-initial rounded bg-label-dark">
											<i class="ti ti-chart-dots"></i>
										</span>
									</div>
									<div>
										<h6 class="mb-0" id="statistics-std-dev">-</h6>
										<small class="text-muted">Desv. Estándar</small>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection

@push('scripts')
	{{ $dataTable->scripts(attributes: ['type' => 'module']) }}

	<script>
		$(document).ready(function () {
			// Hide duplicate search box and DataTables buttons
			$('.dataTables_filter').hide();
			$('.dt-buttons').hide();
			$('.buttons-html5').hide();
			$('.buttons-print').hide();

			// Initialize tooltips
			function initializeTooltips() {
				$('[data-bs-toggle="tooltip"]').tooltip({
					boundary: 'viewport',
					placement: 'auto',
					container: 'body'
				});
			}

			// Initialize tooltips on page load
			initializeTooltips();

			// Validation function for days vs delivery date
			function validateDeliveryOptions() {
				var selectedDays = parseInt($('#days').val()) || 0;
				var deliveryDate = $('#delivery-date').val();

				// If days are selected, ensure delivery date is also selected
				if (selectedDays > 0 && !deliveryDate) {
					console.log('Days selected but no delivery date. Both are required for availability filtering.');
				}

				// If delivery date is selected, ensure days are also selected
				if (deliveryDate && !selectedDays) {
					console.log('Delivery date selected but no days. Both are required for availability filtering.');
				}

				// Check if delivery date is in the past
				if (deliveryDate && selectedDays > 0) {
					var today = new Date();
					today.setHours(0, 0, 0, 0); // Reset time to start of day

					var deliveryDateObj = new Date(deliveryDate);

					if (deliveryDateObj < today) {
						// Show warning message
						toastr.warning('La fecha de entrega no puede ser anterior a hoy. No se encontrarán colaboradores disponibles.', 'Fecha inválida', {
							timeOut: 5000,
							extendedTimeOut: 2000,
							closeButton: true,
							progressBar: true
						});

						// Clear the delivery date
						$('#delivery-date').val('');
						return false;
					}
				}

				return true;
			}

			// Debounce function to prevent multiple rapid calls
			let filterTimeout;
			function debouncedApplyFilters() {
				clearTimeout(filterTimeout);
				filterTimeout = setTimeout(function() {
					// Validate delivery options when days change
					if (this.id === 'days') {
						validateDeliveryOptions();
					}

					var table = $('#collaborator-table').DataTable();

					// Get current filter values
					var sourceLanguage = $('#source-language').length ? $('#source-language').val() : '';
					var targetLanguage = $('#target-language').length ? $('#target-language').val() : '';
					var service = $('#service').val();
					var status = $('#status').val();
					var days = $('#days').val();
					var deliveryDate = $('#delivery-date').val();

					// Clear dashboard filter when using regular filters
					$('.filter-status').removeClass('active-filter');

					// Add parameters to ajax request
					table.settings()[0].ajax.data = function (d) {
						d.source_language = sourceLanguage;
						d.target_language = targetLanguage;
						d.service = service;
						d.status = status;
						d.days = days;
						d.delivery_date = deliveryDate;
						// Don't include dashboard_filter when using regular filters
					};

					// Reload table with new parameters
					table.draw();

					// Fetch service statistics if a service is selected
					console.log('Service filter changed to:', service);
					if (service) {
						fetchServiceStatistics(service);
					} else {
						hideServiceStatistics();
					}
				}, 300); // 300ms delay
			}

			// Table filters
			var languageFilters = $('#source-language, #target-language');
			var otherFilters = $('#service, #status, #days, #delivery-date');
			var allFilters = languageFilters.length ? languageFilters.add(otherFilters) : otherFilters;
			allFilters.on('change', function () {
				// Add event listener for delivery date changes (for date picker)
				if (this.id === 'delivery-date') {
					console.log('Delivery date changed:', $(this).val());
				}

				debouncedApplyFilters();
			});

			// Initialize validation on page load
			validateDeliveryOptions();

			// Add event listener for delivery date changes (for date picker component)
			$(document).on('change', '#delivery-date', function() {
				console.log('Delivery date changed:', $(this).val());
				// Trigger the same filter logic as other filters
				var languageFilters = $('#source-language, #target-language');
				var otherFilters = $('#service, #days');
				var allFilters = languageFilters.length ? languageFilters.add(otherFilters) : otherFilters;
				allFilters.trigger('change');
			});

			// Dashboard filters
			$('.filter-status').on('click', function(e) {
				e.preventDefault();
				var filter = $(this).data('filter');
				var table = $('#collaborator-table').DataTable();

				// Clear other filters first
				var languageFilters = $('#source-language, #target-language');
				var otherFilters = $('#service, #status, #days, #delivery-date');
				var allFilters = languageFilters.length ? languageFilters.add(otherFilters) : otherFilters;
				allFilters.val('');

				// Add the custom filter parameter
				table.settings()[0].ajax.data = function (d) {
					d.dashboard_filter = filter;
				};

				// Reload table with new filter
				table.draw();

				// Visual feedback - add active state
				$('.filter-status').removeClass('active-filter');
				$(this).addClass('active-filter');

				// Hide service statistics when using dashboard filters
				hideServiceStatistics();
			});

			// Clear all filters
			$('#clear-filters').on('click', function(e) {
				e.preventDefault();
				var table = $('#collaborator-table').DataTable();

				// Clear all form filters
				var languageFilters = $('#source-language, #target-language');
				var otherFilters = $('#service, #status, #days, #delivery-date');
				var allFilters = languageFilters.length ? languageFilters.add(otherFilters) : otherFilters;
				allFilters.val('');
				$('#search').val('');

				// Clear dashboard filter active state
				$('.filter-status').removeClass('active-filter');

				// Reset table filters
				table.settings()[0].ajax.data = function (d) {
					// No additional parameters
				};

				// Clear search and reload table
				table.search('').draw();

				// Hide service statistics when filters are cleared
				hideServiceStatistics();
			});

		// Re-initialize tooltips and dropdowns after table draw
		$('#collaborator-table').on('draw.dt', function () {
			initializeTooltips();
			// Initialize Bootstrap dropdowns
			var dropdowns = document.querySelectorAll('[data-bs-toggle="dropdown"]');
			dropdowns.forEach(function(dropdown) {
				if (!dropdown._dropdown) {
					dropdown._dropdown = new bootstrap.Dropdown(dropdown);
				}
			});
		});



			// Entries length
			$('#entries-length').on('change', function () {
				$('#collaborator-table').DataTable().page.len($(this).val()).draw();
			});

			// Search
			$('#search').on('keyup', function () {
				$('#collaborator-table').DataTable().search($(this).val()).draw();
			});

			// Export functionality
			$('#export-csv').on('click', function (e) {
				e.preventDefault();
				setTimeout(function () {
					$('#collaborator-table').DataTable().button('.buttons-csv').trigger();
				}, 100);
			});

			$('#export-pdf').on('click', function (e) {
				e.preventDefault();
				setTimeout(function () {
					$('#collaborator-table').DataTable().button('.buttons-pdf').trigger();
				}, 100);
			});

			// Function to delete a collaborator
			function deleteRecord(id, element) {
				event.preventDefault();
				Swal.fire({
					title: '¿Estás seguro?',
					text: "¿Deseas eliminar este colaborador?",
					icon: 'warning',
					showCancelButton: true,
					confirmButtonText: 'Sí, eliminar',
					cancelButtonText: 'Cancelar',
					customClass: {
						confirmButton: 'btn btn-primary me-3',
						cancelButton: 'btn btn-label-secondary'
					},
					buttonsStyling: false
				}).then(function (result) {
					if (result.isConfirmed) {
						$.ajax({
							url: route('collaborator.destroy', id),
							type: 'DELETE',
							data: {
								_token: $('meta[name="csrf-token"]').attr('content'),
							},
							success: function (response) {
								$('#collaborator-table').DataTable().ajax.reload();
								toastr['success']('', response.message, {
									closeButton: true,
									tapToDismiss: false,
									rtl: false
								});
							},
							error: function (response) {
								Swal.fire({
									title: 'Error',
									text: response.responseJSON.message || 'Ha ocurrido un error',
									icon: 'error',
									customClass: {
										confirmButton: 'btn btn-primary'
									},
									buttonsStyling: false
								});
							}
						});
					}
				});
			}

			// Event delegation for action buttons
			$(document).on('click', '.btn-delete', function () {
				var id = $(this).data('id');
				deleteRecord(id, this);
			});

		// Event delegation for status change
		$(document).on('click', '.change-status', function (e) {
			e.preventDefault();
			e.stopPropagation(); // Prevent event bubbling
			var contactId = $(this).data('contact-id');
			var statusId = $(this).data('status-id');
			console.log('Status change clicked:', { contactId, statusId }); // Debug log
			changeCollaboratorStatus(contactId, statusId);
		});

							// Function to fetch service statistics
		function fetchServiceStatistics(serviceId) {
			console.log('Fetching statistics for service ID:', serviceId);

			// Get current filter values to pass to statistics API
			var sourceLanguage = $('#source-language').length ? $('#source-language').val() : '';
			var targetLanguage = $('#target-language').length ? $('#target-language').val() : '';
			var status = $('#status').val();
			var days = $('#days').val();
			var deliveryDate = $('#delivery-date').val();

			$.ajax({
				url: '{{ url("/api/collaborator/service-statistics") }}',
				type: 'GET',
				data: {
					service_id: serviceId,
					team_id: {{ auth()->user()->currentTeam->id ?? 4 }}, // Use current team or default to 4
					source_language: sourceLanguage,
					target_language: targetLanguage,
					status: status,
					days: days,
					delivery_date: deliveryDate
				},
					success: function (response) {
						console.log('Statistics response:', response);
						if (response.success) {
							displayServiceStatistics(response);
						} else {
							hideServiceStatistics();
							// Clear any existing toasts first
							toastr.clear();

							// Check if this is an availability issue
							var hasAvailabilityFilter = $('#days').val() && $('#delivery-date').val();
							var message = response.message;

							if (hasAvailabilityFilter && message.includes('disponibilidad')) {
								message += ' (La tabla muestra colaboradores con el servicio, pero sin disponibilidad suficiente)';
							}

							// Show only one warning message
							toastr['warning']('', message, {
								closeButton: true,
								tapToDismiss: false,
								rtl: false,
								timeOut: 10000,
								extendedTimeOut: 2000
							});
						}
					},
					error: function (xhr, status, error) {
						console.error('Error fetching statistics:', {xhr: xhr, status: status, error: error});
						hideServiceStatistics();
						var message = 'Error al obtener estadísticas del servicio';
						if (xhr.responseJSON && xhr.responseJSON.message) {
							message = xhr.responseJSON.message;
						}
						// Clear any existing toasts first
						toastr.clear();
						// Show only one warning message
						toastr['warning']('', message, {
							closeButton: true,
							tapToDismiss: false,
							rtl: false,
							timeOut: 8000,
							extendedTimeOut: 2000
						});
					}
				});
			}

						// Function to display service statistics
			function displayServiceStatistics(data) {
				$('#statistics-service-name').text('Estadísticas: ' + data.service_name);
				$('#statistics-media').text('€' + data.statistics.media);
				$('#statistics-mediana').text('€' + data.statistics.mediana);

				// Handle moda (mode) - can be null or array
				if (data.statistics.moda && data.statistics.moda.length > 0) {
					var modaText = data.statistics.moda.map(function(value) {
						return '€' + value;
					}).join(', ');
					$('#statistics-moda').text(modaText);
				} else {
					$('#statistics-moda').text('No hay moda');
				}

				$('#statistics-count').text(data.statistics.count);
				$('#statistics-min').text('€' + data.statistics.min);
				$('#statistics-max').text('€' + data.statistics.max);
				$('#statistics-range').text('€' + data.statistics.range);
				$('#statistics-std-dev').text('€' + data.statistics.standard_deviation);

				$('#service-statistics').show().addClass('show');
			}

			// Function to hide service statistics
			function hideServiceStatistics() {
				$('#service-statistics').removeClass('show').hide();
			}
		});

		// Function to mark as watch
		function markAsWatch(id) {
			Swal.fire({
				title: '¿Marcar como ojo?',
				text: "Este colaborador será marcado para supervisión especial",
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: 'Sí, marcar',
				cancelButtonText: 'Cancelar',
				customClass: {
					confirmButton: 'btn btn-warning me-3',
					cancelButton: 'btn btn-label-secondary'
				},
				buttonsStyling: false
			}).then(function (result) {
				if (result.isConfirmed) {
					$.ajax({
						url: '/collaborator/' + id + '/mark-as-watch',
						type: 'POST',
						data: {
							_token: $('meta[name="csrf-token"]').attr('content'),
						},
						success: function (response) {
							toastr['success']('', response.message, {
								closeButton: true,
								tapToDismiss: false,
								rtl: false
							});
							$('#collaborator-table').DataTable().ajax.reload();
						},
						error: function (response) {
							Swal.fire({
								title: 'Error',
								text: response.responseJSON.message || 'Ha ocurrido un error',
								icon: 'error',
								customClass: {
									confirmButton: 'btn btn-primary'
								},
								buttonsStyling: false
							});
						}
					});
				}
			});
		}

		// Function to send to blacklist
		function sendToBlacklist(id) {
			Swal.fire({
				title: '¿Enviar a lista negra?',
				text: "Este colaborador será bloqueado y no podrá participar en proyectos",
				icon: 'error',
				showCancelButton: true,
				confirmButtonText: 'Sí, bloquear',
				cancelButtonText: 'Cancelar',
				customClass: {
					confirmButton: 'btn btn-danger me-3',
					cancelButton: 'btn btn-label-secondary'
				},
				buttonsStyling: false
			}).then(function (result) {
				if (result.isConfirmed) {
					$.ajax({
						url: '/collaborator/' + id + '/send-to-blacklist',
						type: 'POST',
						data: {
							_token: $('meta[name="csrf-token"]').attr('content'),
						},
						success: function (response) {
							toastr['success']('', response.message, {
								closeButton: true,
								tapToDismiss: false,
								rtl: false
							});
							$('#collaborator-table').DataTable().ajax.reload();
						},
						error: function (response) {
							Swal.fire({
								title: 'Error',
								text: response.responseJSON.message || 'Ha ocurrido un error',
								icon: 'error',
								customClass: {
									confirmButton: 'btn btn-primary'
								},
								buttonsStyling: false
							});
						}
					});
				}
			});
		}

		// Function to send notification
		function sendNotification(id) {
			Swal.fire({
				title: 'Enviar notificación',
				input: 'textarea',
				inputLabel: 'Mensaje de notificación',
				inputPlaceholder: 'Escribe tu mensaje aquí...',
				inputAttributes: {
					'aria-label': 'Escribe tu mensaje de notificación'
				},
				showCancelButton: true,
				confirmButtonText: 'Enviar',
				cancelButtonText: 'Cancelar',
				customClass: {
					confirmButton: 'btn btn-primary me-3',
					cancelButton: 'btn btn-label-secondary'
				},
				buttonsStyling: false,
				inputValidator: (value) => {
					if (!value) {
						return 'Debes escribir un mensaje'
					}
				}
			}).then(function (result) {
				if (result.isConfirmed) {
					$.ajax({
						url: '/collaborator/' + id + '/send-notification',
						type: 'POST',
						data: {
							_token: $('meta[name="csrf-token"]').attr('content'),
							message: result.value
						},
						success: function (response) {
							toastr['success']('', response.message, {
								closeButton: true,
								tapToDismiss: false,
								rtl: false
							});
						},
						error: function (response) {
							Swal.fire({
								title: 'Error',
								text: response.responseJSON.message || 'Ha ocurrido un error',
								icon: 'error',
								customClass: {
									confirmButton: 'btn btn-primary'
								},
								buttonsStyling: false
							});
						}
					});
				}
			});
		}

	// Function to change collaborator status
	function changeCollaboratorStatus(contactId, statusId) {
		console.log('Attempting to change status:', { contactId, statusId }); // Debug log

		// Verify CSRF token exists
		var csrfToken = $('meta[name="csrf-token"]').attr('content');
		if (!csrfToken) {
			console.error('CSRF token not found');
			toastr['error']('', 'Error de seguridad: Token CSRF no encontrado', {
				closeButton: true,
				tapToDismiss: false,
				rtl: false
			});
			return;
		}

		$.ajax({
			url: '/collaborator/' + contactId + '/update-status',
			type: 'POST',
			data: {
				_token: csrfToken,
				status_id: statusId
			},
			beforeSend: function() {
				console.log('Sending request to update status...'); // Debug log
			},
			success: function (response) {
				console.log('Status update response:', response); // Debug log
				if (response.success) {
					toastr['success']('', response.message, {
						closeButton: true,
						tapToDismiss: false,
						rtl: false
					});
					// Reload the table to show updated status
					$('#collaborator-table').DataTable().ajax.reload();
				} else {
					toastr['error']('', response.message || 'Error desconocido', {
						closeButton: true,
						tapToDismiss: false,
						rtl: false
					});
				}
			},
			error: function (xhr, status, error) {
				console.error('Status update error:', { xhr, status, error }); // Debug log
				var message = 'Error al actualizar el estado';

				if (xhr.responseJSON && xhr.responseJSON.message) {
					message = xhr.responseJSON.message;
				} else if (xhr.status === 403) {
					message = 'No tienes permisos para realizar esta acción';
				} else if (xhr.status === 404) {
					message = 'Recurso no encontrado';
				} else if (xhr.status === 500) {
					message = 'Error interno del servidor';
				}

				toastr['error']('', message, {
					closeButton: true,
					tapToDismiss: false,
					rtl: false
				});
			}
		});
	}
	</script>

			<script>
			$(function () {
				// Initialize Flatpickr for delivery date with minimum date
				if (typeof flatpickr !== 'undefined') {
					const today = new Date().toISOString().split('T')[0];
					flatpickr('#delivery-date', {
						dateFormat: 'Y-m-d',
						allowInput: true,
						altInput: true,
						altFormat: 'd-m-Y',
						minDate: today,
						monthSelectorType: 'static'
					});
				}
			if ($.fn.select2) {
				if ($('#source-language').length) {
					if ($('#source-language').hasClass('select2-hidden-accessible')) {
						$('#source-language').select2('destroy');
					}
					$('#source-language').select2({
						allowClear: true,
						placeholder: '{{ __("Idioma origen") }}',
						width: '100%',
						templateResult: window.formatVariantLanguage || function(lang) { return lang.text; },
						templateSelection: window.formatVariantLanguage || function(lang) { return lang.text; }
					});
				}

				if ($('#target-language').length) {
					if ($('#target-language').hasClass('select2-hidden-accessible')) {
						$('#target-language').select2('destroy');
					}
					$('#target-language').select2({
						allowClear: true,
						placeholder: '{{ __("Idioma destino") }}',
					width: '100%',
					templateResult: window.formatVariantLanguage || function(lang) { return lang.text; },
					templateSelection: window.formatVariantLanguage || function(lang) { return lang.text; }
				});
			}
		});
	</script>
@endpush
