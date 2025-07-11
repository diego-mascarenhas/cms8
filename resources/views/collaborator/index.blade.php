@extends('layouts/layoutMaster')

@section('title', __('Collaborators'))

@section('vendor-style')
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
@endsection

@section('vendor-script')
	<script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
	<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
	<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
	<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
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

	<div class="card shadow">
		<div class="card-body">
			<h5 class="mb-3">Filtros</h5>
			<div class="row g-3 mb-3">
				<div class="col">
					<x-variant-language-select name="source-language" id="source-language" label="" :required="false"
						placeholder="{{ __('Idioma origen') }}" />
				</div>
				<div class="col">
					<x-variant-language-select name="target-language" id="target-language" label="" :required="false"
						placeholder="{{ __('Idioma destino') }}" />
				</div>
				<div class="col">
					<x-fare-select name="service" id="service" label="" :required="false"
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
					<a href="{{ route('collaborator.create') }}"
						class="btn btn-primary ms-2 d-flex align-items-center gap-1"
						style="height: 40px; min-width: 170px;">
						<i class="ti ti-plus"></i>
						<span style="white-space: nowrap;">{{ __('Añadir nuevo') }}</span>
					</a>
					<button class="btn btn-outline-secondary ms-2" id="clear-filters" style="height: 40px;" title="{{ __('Limpiar filtros') }}">
						<i class="ti ti-refresh"></i>
					</button>
				</div>
			</div>

			<hr>

			{{ $dataTable->table(['class' => 'table table-hover table-striped dt-responsive nowrap w-100']) }}
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

			// Table filters
			$('#source-language, #target-language, #service, #days, #delivery-date').on('change', function () {
				// Validate delivery options when days change
				if (this.id === 'days') {
					validateDeliveryOptions();
				}
				
				var table = $('#collaborator-table').DataTable();

				// Get current filter values
				var sourceLanguage = $('#source-language').val();
				var targetLanguage = $('#target-language').val();
				var service = $('#service').val();
				var days = $('#days').val();
				var deliveryDate = $('#delivery-date').val();

				// Clear dashboard filter when using regular filters
				$('.filter-status').removeClass('active-filter');

				// Add parameters to ajax request
				table.settings()[0].ajax.data = function (d) {
					d.source_language = sourceLanguage;
					d.target_language = targetLanguage;
					d.service = service;
					d.days = days;
					d.delivery_date = deliveryDate;
					// Don't include dashboard_filter when using regular filters
				};

				// Reload table with new parameters
				table.draw();
			});

			// Initialize validation on page load
			validateDeliveryOptions();

			// Dashboard filters
			$('.filter-status').on('click', function(e) {
				e.preventDefault();
				var filter = $(this).data('filter');
				var table = $('#collaborator-table').DataTable();

				// Clear other filters first
				$('#source-language, #target-language, #service, #days, #delivery-date').val('');

				// Add the custom filter parameter
				table.settings()[0].ajax.data = function (d) {
					d.dashboard_filter = filter;
				};

				// Reload table with new filter
				table.draw();

				// Visual feedback - add active state
				$('.filter-status').removeClass('active-filter');
				$(this).addClass('active-filter');
			});

			// Clear all filters
			$('#clear-filters').on('click', function(e) {
				e.preventDefault();
				var table = $('#collaborator-table').DataTable();

				// Clear all form filters
				$('#source-language, #target-language, #service, #days, #delivery-date').val('');
				$('#search').val('');

				// Clear dashboard filter active state
				$('.filter-status').removeClass('active-filter');

				// Reset table filters
				table.settings()[0].ajax.data = function (d) {
					// No additional parameters
				};

				// Clear search and reload table
				table.search('').draw();
			});

			// Re-initialize tooltips after table draw
			$('#collaborator-table').on('draw.dt', function () {
				initializeTooltips();
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
	</script>
@endpush