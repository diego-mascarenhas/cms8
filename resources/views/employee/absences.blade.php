@extends('layouts/layoutMaster')

@section('title', 'Employee Availability')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
<meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('page-style')
<style>
	.calendar-grid {
		display: grid;
		grid-template-columns: repeat(7, 1fr);
		gap: 2px;
		text-align: center;
	}

	.calendar-day {
		aspect-ratio: 1;
		display: flex;
		align-items: center;
		justify-content: center;
		cursor: pointer;
		border-radius: 50%;
		margin: 1px;
		font-size: 0.9rem;
		transition: all 0.2s;
	}

	.calendar-day:hover:not(.day-disabled):not(.weekly-unavailable) {
		background-color: #f0f0f0;
	}

	.calendar-header {
		font-weight: bold;
		padding: 8px 0;
		background-color: #f9f9f9;
		border-radius: 5px;
	}

	.day-disabled {
		opacity: 0.3;
		cursor: default;
	}

	.day-unavailable {
		background-color: #ffebeb;
		color: #e55353;
		font-weight: bold;
	}

	.weekly-unavailable {
		background-color: #f8d7da;
		color: #842029;
		cursor: not-allowed;
		opacity: 0.7;
	}

	.day-today {
		border: 2px solid #696cff;
	}

	.weekday-toggle {
		border-radius: 0;
		flex: 1;
	}

	.weekday-toggle.active {
		background-color: #e55353;
		color: white;
	}

	.weekday-toggle:not(.active) {
		background-color: #f0f0f0;
	}

	.btn-danger {
		background-color: #e55353 !important;
		border-color: #e55353 !important;
	}
</style>
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3">Disponibilidad del Empleado</h4>
		<p class="text-muted">Gestiona la disponibilidad de {{ $employee->name }}</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3">
		<a href="{{ route('employee.show', $employee->id) }}" class="btn btn-primary waves-effect waves-light">
			<i class="ti ti-arrow-left me-1"></i>Volver al Empleado
		</a>
	</div>
</div>

<div class="row">
	<!-- Employee Sidebar -->
	<div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
		<div class="card mb-4">
			<div class="card-body">
				<div class="d-flex align-items-center flex-column">
					<img class="img-fluid rounded mb-3 pt-1 mt-4"
						src="{{ \App\Helpers\AvatarHelper::generate($employee->name, 100) }}" height="100"
						width="100" alt="Employee avatar" />
					<div class="user-info text-center">
						<h4 class="mb-2">{{ $employee->name }}</h4>
						@if($employee->data->command ?? false)
							<span class="badge bg-label-secondary mt-1">#{{ $employee->data->command }}</span>
						@endif
					</div>
				</div>
				<div class="mt-4">
					<ul class="list-unstyled">
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">Email:</span>
							<span>{{ $employee->email }}</span>
						</li>
						@if($employee->phone)
							<li class="mb-2 pt-1">
								<span class="fw-medium me-1">Teléfono:</span>
								<span>{{ $employee->phone }}</span>
							</li>
						@endif
						@if($employee->data->city ?? false)
							<li class="mb-2 pt-1">
								<span class="fw-medium me-1">Ciudad:</span>
								<span>{{ $employee->data->city }}</span>
							</li>
						@endif
						@if($employee->data->province ?? false)
							<li class="mb-2 pt-1">
								<span class="fw-medium me-1">Provincia:</span>
								<span>{{ $employee->data->province }}</span>
							</li>
						@endif
					</ul>
				</div>
			</div>
		</div>
	</div>

	<!-- Availability Content -->
	<div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="card-title mb-0">Períodos de no disponibilidad</h5>
				<p class="card-subtitle text-muted mt-1">Selecciona los períodos en los que el empleado no estará disponible.</p>
				<p class="text-muted">Esto nos ayudará a planificar mejor los proyectos y asignaciones.</p>
			</div>
			<div class="card-body">
				<h6 class="mb-3">Disponibilidad por día de la semana</h6>
				<div class="d-flex flex-wrap mb-4">
					<div class="btn-group w-100 mb-3">
						<button type="button" class="btn {{ $weeklyAvailability->monday ? 'btn-outline-secondary' : 'btn-danger text-white' }}" data-day="monday">Lunes</button>
						<button type="button" class="btn {{ $weeklyAvailability->tuesday ? 'btn-outline-secondary' : 'btn-danger text-white' }}" data-day="tuesday">Martes</button>
						<button type="button" class="btn {{ $weeklyAvailability->wednesday ? 'btn-outline-secondary' : 'btn-danger text-white' }}" data-day="wednesday">Miércoles</button>
						<button type="button" class="btn {{ $weeklyAvailability->thursday ? 'btn-outline-secondary' : 'btn-danger text-white' }}" data-day="thursday">Jueves</button>
						<button type="button" class="btn {{ $weeklyAvailability->friday ? 'btn-outline-secondary' : 'btn-danger text-white' }}" data-day="friday">Viernes</button>
						<button type="button" class="btn {{ $weeklyAvailability->saturday ? 'btn-outline-secondary' : 'btn-danger text-white' }}" data-day="saturday">Sábado</button>
						<button type="button" class="btn {{ $weeklyAvailability->sunday ? 'btn-outline-secondary' : 'btn-danger text-white' }}" data-day="sunday">Domingo</button>
					</div>
					<p class="text-muted w-100 mt-2">Los días marcados en <strong class="text-danger">rojo</strong> indican que el empleado <strong class="text-danger">NO</strong> está disponible ese día de la semana.</p>
				</div>

				<h6 class="mb-3">Fechas específicas de no disponibilidad</h6>
				<p class="text-muted mb-4">Selecciona los días específicos en los que no estará disponible.</p>

				<div class="row">
					@foreach($months as $index => $month)
						<div class="col-md-6 col-lg-4 mb-4">
							<div class="card h-100">
								<div class="card-body">
									<h5 class="text-center mb-4">{{ $month['name'] }}</h5>
									<div class="calendar-grid">
										<div class="calendar-header">Do</div>
										<div class="calendar-header">Lu</div>
										<div class="calendar-header">Ma</div>
										<div class="calendar-header">Mi</div>
										<div class="calendar-header">Ju</div>
										<div class="calendar-header">Vi</div>
										<div class="calendar-header">Sa</div>

										@for($i = 0; $i < $month['startPadding']; $i++)
											<div class="calendar-day day-disabled"></div>
										@endfor

										@for($day = 1; $day <= $month['daysInMonth']; $day++)
											@php
												$date = $month['year'] . '-' . str_pad($month['month'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
												$isUnavailable = in_array($date, $absences);
												$isToday = date('Y-m-d') === $date;

												// Determine day of week (0 = sunday, 1 = monday, etc.)
												$dayOfWeek = date('w', strtotime($date));

												// Check if day of week is marked as unavailable
												$weeklyUnavailable = false;
												switch($dayOfWeek) {
													case 0: $weeklyUnavailable = !$weeklyAvailability->sunday; break;
													case 1: $weeklyUnavailable = !$weeklyAvailability->monday; break;
													case 2: $weeklyUnavailable = !$weeklyAvailability->tuesday; break;
													case 3: $weeklyUnavailable = !$weeklyAvailability->wednesday; break;
													case 4: $weeklyUnavailable = !$weeklyAvailability->thursday; break;
													case 5: $weeklyUnavailable = !$weeklyAvailability->friday; break;
													case 6: $weeklyUnavailable = !$weeklyAvailability->saturday; break;
												}

												$classes = [];
												if ($isUnavailable) $classes[] = 'day-unavailable';
												if ($weeklyUnavailable) $classes[] = 'weekly-unavailable';
												if ($isToday) $classes[] = 'day-today';
												$classString = implode(' ', $classes);
											@endphp
											<div class="calendar-day {{ $classString }}"
												 data-date="{{ $date }}"
												 data-weekly-unavailable="{{ $weeklyUnavailable ? 'true' : 'false' }}">
												{{ $day }}
											</div>
										@endfor
									</div>
								</div>
							</div>
						</div>
					@endforeach
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
	// CSRF Token setup for AJAX
	const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
	const employeeId = {{ $employee->id }};

	// Weekly availability toggle
	const weekdayToggles = document.querySelectorAll('.btn-group button');
	const weeklyAvailability = {
		monday: {{ $weeklyAvailability->monday ? 'true' : 'false' }},
		tuesday: {{ $weeklyAvailability->tuesday ? 'true' : 'false' }},
		wednesday: {{ $weeklyAvailability->wednesday ? 'true' : 'false' }},
		thursday: {{ $weeklyAvailability->thursday ? 'true' : 'false' }},
		friday: {{ $weeklyAvailability->friday ? 'true' : 'false' }},
		saturday: {{ $weeklyAvailability->saturday ? 'true' : 'false' }},
		sunday: {{ $weeklyAvailability->sunday ? 'true' : 'false' }}
	};

	weekdayToggles.forEach(toggle => {
		toggle.addEventListener('click', function() {
			const day = this.getAttribute('data-day');

			if (this.classList.contains('btn-danger')) {
				this.classList.remove('btn-danger');
				this.classList.remove('text-white');
				this.classList.add('btn-outline-secondary');
				weeklyAvailability[day] = true;
			} else {
				this.classList.remove('btn-outline-secondary');
				this.classList.add('btn-danger');
				this.classList.add('text-white');
				weeklyAvailability[day] = false;
			}

			// Send update to server
			fetch('{{ route('employee.absences.update-weekly', $employee->id) }}', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': csrfToken
				},
				body: JSON.stringify(weeklyAvailability)
			})
			.then(response => response.json())
			.then(data => {
				if (data.status === 'success') {
					// Update calendar to reflect change without refreshing
					updateCalendarForWeekday(day, !weeklyAvailability[day]);

					Swal.fire({
						title: 'Actualizado',
						text: 'La disponibilidad semanal ha sido actualizada',
						icon: 'success',
						customClass: {
							confirmButton: 'btn btn-primary'
						},
						buttonsStyling: false
					});
				}
			})
			.catch(error => console.error('Error:', error));
		});
	});

	// Function to update calendar when weekday changes
	function updateCalendarForWeekday(day, isUnavailable) {
		// Map day names to their corresponding numbers in JavaScript Date
		// Adjusted to use monday as first day: 0=monday, 1=tuesday, etc., 6=sunday
		const dayOfWeekMap = {
			'monday': 0,
			'tuesday': 1,
			'wednesday': 2,
			'thursday': 3,
			'friday': 4,
			'saturday': 5,
			'sunday': 6
		};

		const dayNumber = dayOfWeekMap[day];

		// Update all calendar days that correspond to the modified weekday
		document.querySelectorAll('.calendar-day:not(.day-disabled)').forEach(calendarDay => {
			const date = calendarDay.getAttribute('data-date');

			// Get day of week from date adjusted to start on monday
			let dateDayOfWeek = new Date(date).getDay(); // 0=sunday, 1=monday, ..., 6=saturday

			// Convert to format where monday=0, tuesday=1, ..., sunday=6
			dateDayOfWeek = dateDayOfWeek === 0 ? 6 : dateDayOfWeek - 1;

			// If this calendar day corresponds to the modified weekday
			if (dateDayOfWeek === dayNumber) {
				if (isUnavailable) {
					// Mark as unavailable by weekly pattern
					calendarDay.classList.add('weekly-unavailable');
					calendarDay.setAttribute('data-weekly-unavailable', 'true');

					// If it was manually marked as unavailable, remove that mark
					// since it's now unavailable by weekly pattern
					calendarDay.classList.remove('day-unavailable');
				} else {
					// Mark as available
					calendarDay.classList.remove('weekly-unavailable');
					calendarDay.setAttribute('data-weekly-unavailable', 'false');
				}
			}
		});
	}

	// Date availability toggle
	const calendarDays = document.querySelectorAll('.calendar-day:not(.day-disabled)');

	calendarDays.forEach(day => {
		day.addEventListener('click', function() {
			// Don't allow selecting days marked as unavailable by weekly pattern
			if (this.getAttribute('data-weekly-unavailable') === 'true') {
				Swal.fire({
					title: 'Día no seleccionable',
					text: 'Este día no está disponible según el patrón semanal configurado.',
					icon: 'info',
					customClass: {
						confirmButton: 'btn btn-primary'
					},
					buttonsStyling: false
				});
				return;
			}

			const date = this.getAttribute('data-date');

			// Send update to server
			fetch('{{ route('employee.absences.toggle-date', $employee->id) }}', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': csrfToken
				},
				body: JSON.stringify({ date: date })
			})
			.then(response => response.json())
			.then(data => {
				if (data.status === 'unavailable') {
					this.classList.add('day-unavailable');
				} else {
					this.classList.remove('day-unavailable');
				}
			})
			.catch(error => console.error('Error:', error));
		});
	});
});
</script>
@endsection
