@extends('layouts/layoutMaster')

@section('title', __('Times'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/ui-toasts.js')}}"></script>
@endsection

<style>
	.fade-out {
		opacity: 0;
		transition: opacity 0.5s ease-out;
	}
	.timer-display {
		font-size: 2.5rem;
		font-weight: 600;
		font-family: monospace;
	}
	.timer-card {
		border-left: 4px solid #696cff;
	}
</style>

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3">{{ __('Times') }}</h4>
		<p class="text-muted">{{ __('Track your work hours') }}</p>
	</div>
	@can('time.create')
	<div class="mt-3 mt-md-0">
		<a href="{{ route('time.create') }}" class="btn btn-primary"> <i class="ti ti-plus me-1"></i> {{ __('Add Manual Entry') }} </a>
	</div>
	@endcan
</div>

@if(session('success'))
<div id="toast-container" class="toast-top-right">
	<div class="toast toast-success" aria-live="polite" style="display: block;">
		<div class="toast-message">{{ session('success') }}</div>
	</div>
</div>
@endif

<!-- Timer Widget -->
<div class="card timer-card mb-4">
	<div class="card-body">
		<div class="row align-items-center">
			<div class="col-md-4">
				<h5 class="mb-0"><i class="ti ti-clock me-2"></i>{{ __('Quick Timer') }}</h5>
				<p class="text-muted mb-0 small">{{ __('Start tracking time instantly') }}</p>
			</div>
			<div class="col-md-4 text-center">
				<div id="timer-display" class="timer-display text-primary">
					@if($runningTimer)
						{{ now()->diff($runningTimer->start_time)->format('%H:%I:%S') }}
					@else
						00:00:00
					@endif
				</div>
			</div>
			<div class="col-md-4 text-end">
				<div id="timer-controls">
					@if($runningTimer)
						<button type="button" class="btn btn-danger" id="stop-timer-btn" data-timer-id="{{ $runningTimer->id }}">
							<i class="ti ti-player-stop me-1"></i>{{ __('Stop Timer') }}
						</button>
					@endif
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Time Entries Table -->
<div class="card">
	<div class="card-body">
		{{ $dataTable->table() }}
	</div>
</div>

<script>
let timerInterval;
let timerRunning = {{ $runningTimer ? 'true' : 'false' }};
let startTime = {{ $runningTimer ? $runningTimer->start_time->timestamp : 'null' }};

// Update timer display
function updateTimer() {
	if (!timerRunning) return;

	const now = Math.floor(Date.now() / 1000);
	const elapsed = now - startTime;

	const hours = Math.floor(elapsed / 3600);
	const minutes = Math.floor((elapsed % 3600) / 60);
	const seconds = elapsed % 60;

	document.getElementById('timer-display').textContent =
		`${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

// (Quick timer controls removed on this page)

// Start timer
document.addEventListener('DOMContentLoaded', function() {
	if (timerRunning) {
		timerInterval = setInterval(updateTimer, 1000);
		updateTimer();
	}

	// Stop button only (quick start removed)
	const stopBtn = document.getElementById('stop-timer-btn');
	if (stopBtn) {
		stopBtn.addEventListener('click', function() {
			const timerId = this.dataset.timerId;
			fetch(`/time/${timerId}/stop`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': '{{ csrf_token() }}'
				}
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					toastr.success(`{{ __("Timer stopped") }}: ${data.duration}`);
					setTimeout(() => location.reload(), 1000);
				} else {
					toastr.error(data.message);
				}
			})
			.catch(error => {
				console.error('Error:', error);
				toastr.error('{{ __("An error occurred") }}');
			});
		});
	}
});

function deleteRecord(id, element) {
	Swal.fire({
		title: '{{ __("Are you sure you want to delete this record?") }}',
		text: '{{ __("This action cannot be undone") }}',
		icon: 'warning',
		showCloseButton: false,
		showCancelButton: true,
		confirmButtonColor: '#d33',
		confirmButtonText: '{{ __("Yes, delete") }}'
	}).then((result) => {
		if (result.isConfirmed) {
			fetch("{{ route('time.destroy', ['id' => ':ID']) }}".replace(':ID', id), {
				method: 'DELETE',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': '{{ csrf_token() }}'
				}
			}).then(response => {
				if (!response.ok) {
					throw new Error('Network response was not ok.');
				}
				return response.json();
			}).then(data => {
				toastr.success('{{ __("Record deleted successfully") }}');

				const row = element.closest('tr');
				if (row) {
					row.classList.add('fade-out');
					row.addEventListener('transitionend', () => {
						row.remove();
					});
				}
			}).catch(error => {
				console.error('Error:', error);
				Swal.fire('{{ __("Error") }}', '{{ __("An error occurred while deleting the record") }}', 'error');
			});
		}
	});
}
</script>
@endsection

@push('scripts')
	{{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush

@section('vendor-script')
<script src="{{asset('vendors/data-tables/js/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendors/data-tables/extensions/responsive/js/dataTables.responsive.min.js')}}"></script>
<script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>
<script src="{{asset('vendors/fullcalendar/lib/moment.min.js')}}"></script>
<script src="{{asset('js/moment/' . app()->getLocale() . '.js')}}"></script>
@endsection
