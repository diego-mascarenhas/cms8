@extends('layouts/layoutMaster')

@section('title', 'Messages')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
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
</style>

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Messages') }}</h4>
        <p class="text-muted">{{ __('Manage your messages with ease and keep your audience engaged!') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('message.create') }}" type="submit" class="btn btn-primary waves-effect waves-light">
            <i class="ti ti-plus me-1"></i>{{ __('Create New') }}
        </a>
    </div>
</div>

<!-- Statistics Cards -->
@php
	$team = auth()->user()->currentTeam;
	$currentPlan = $team->getEmailPlan();
	$planConfig = $team->getEmailPlanConfig();
@endphp

<div class="row mb-4">
	<!-- Plan Actual -->
	<div class="col-xl-3 col-md-6 col-sm-6 mb-4">
		<div class="card h-100">
			<div class="card-body d-flex justify-content-between align-items-center">
				<div class="card-title mb-0">
					<h5 class="mb-0 me-2">{{ $currentPlan->getDisplayName() }}</h5>
					<small>Plan Actual</small>
				</div>
				<div class="card-icon">
					<span class="badge bg-label-primary rounded-pill p-2">
						<i class='ti ti-award ti-sm'></i>
					</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Contactos -->
	<div class="col-xl-3 col-md-6 col-sm-6 mb-4">
		<div class="card h-100">
			<div class="card-body d-flex justify-content-between align-items-center">
				<div class="card-title mb-0">
					<h5 class="mb-0 me-2">{{ number_format($team->contacts()->count()) }}</h5>
					<small>Contactos / {{ number_format($planConfig['contact_limit']) }}</small>
				</div>
				<div class="card-icon">
					<span class="badge bg-label-info rounded-pill p-2">
						<i class='ti ti-users ti-sm'></i>
					</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Envíos Mensuales -->
	<div class="col-xl-3 col-md-6 col-sm-6 mb-4">
		<div class="card h-100">
			<div class="card-body d-flex justify-content-between align-items-center">
				<div class="card-title mb-0">
					<h5 class="mb-0 me-2">{{ number_format($planConfig['monthly_used']) }}</h5>
					<small>Envíos este mes / {{ number_format($planConfig['monthly_limit']) }}</small>
				</div>
				<div class="card-icon">
					<span class="badge bg-label-success rounded-pill p-2">
						<i class='ti ti-send ti-sm'></i>
					</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Envíos Diarios -->
	<div class="col-xl-3 col-md-6 col-sm-6 mb-4">
		<div class="card h-100">
			<div class="card-body d-flex justify-content-between align-items-center">
				<div class="card-title mb-0">
					<h5 class="mb-0 me-2">{{ number_format($planConfig['daily_used']) }}</h5>
					<small>Envíos hoy / {{ $planConfig['daily_limit'] ? number_format($planConfig['daily_limit']) : '∞' }}</small>
				</div>
				<div class="card-icon">
					<span class="badge bg-label-warning rounded-pill p-2">
						<i class='ti ti-clock ti-sm'></i>
					</span>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Email Plan Limits Alert -->
@php
	$team = auth()->user()->currentTeam;
	$currentPlan = $team->getEmailPlan();
	$planConfig = $team->getEmailPlanConfig();
@endphp

@if($currentPlan === \App\Enums\EmailPlan::FREE)
<div class="alert alert-info d-flex align-items-center mb-3" role="alert">
	<div class="flex-grow-1">
		<i class="ti ti-info-circle me-2"></i>
		<strong>Plan FREE:</strong>
		Estás usando el plan gratuito con {{ number_format($planConfig['monthly_limit']) }} envíos/mes,
		{{ number_format($planConfig['daily_limit']) }} envíos/día y hasta {{ number_format($planConfig['contact_limit']) }} contactos.
		<br>
		<small>
			Usados este mes: {{ number_format($planConfig['monthly_used']) }}/{{ number_format($planConfig['monthly_limit']) }}
			| Hoy: {{ number_format($planConfig['daily_used']) }}/{{ number_format($planConfig['daily_limit']) }}
		</small>
	</div>
	<a href="{{ route('subscription.index') }}" class="btn btn-sm btn-primary ms-3">
		<i class="ti ti-rocket me-1"></i>Actualizar Plan
	</a>
</div>
@endif

@if(session('success'))
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

<div class="card">
    <div class="card-body">
        {{ $dataTable->table() }}
    </div>
</div>

<script>
    function deleteRecord(id, element) {
        Swal.fire({
            title: 'Are you sure you want to delete this record?',
            text: 'This action cannot be undone',
            icon: 'warning',
            showCloseButton: false,
            showCancelButton: false,
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("{{ route('message.destroy', ['id' => ':ID']) }}".replace(':ID', id), {
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
                    console.log('Response data:', data);

                    const toastHTML = `
                        <div id="toast-container" class="toast-top-right">
                            <div class="toast toast-success" aria-live="polite" style="display: block;">
                                <div class="toast-message">${data.success}</div>
                            </div>
                        </div>
                    `;
                    document.body.insertAdjacentHTML('beforeend', toastHTML);
                    var toastElement = document.getElementById('toast-container');
                    var toast = new bootstrap.Toast(toastElement, {
                        animation: true,
                        delay: 3000,
                        autohide: true
                    });
                    toast.show();

                    const row = element.closest('tr');
                    if (row) {
                        row.classList.add('fade-out');
                        row.addEventListener('transitionend', () => {
                            row.remove();
                        });
                    } else {
                        console.error('No se encontró la fila correspondiente.');
                    }
                }).catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Ha ocurrido un error al eliminar el registro', 'error');
                });
            }
        });
    }
</script>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
