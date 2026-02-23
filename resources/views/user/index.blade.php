@extends('layouts/layoutMaster')

@section('title', 'Users')

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
</style>

@section('content')
	<!-- Header following project pattern -->
	<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
		<div class="d-flex flex-column justify-content-center">
			<h4 class="mb-1 mt-3">{{ __('Users') }}</h4>
			<p class="text-muted">{{ __('Manage team users and their permissions') }}</p>
		</div>
	</div>

	@if (session('success'))
		<div id="toast-container" class="toast-top-right">
			<div class="toast toast-success" aria-live="polite" style="display: block;">
				<div class="toast-client">{{ session('success') }}</div>
			</div>
		</div>

		<script>
			document.addEventListener('DOMContentLoaded', function() {
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

	<!-- Statistics Cards -->
	<div class="row g-4 mb-4">
		<div class="col-sm-6 col-xl-3">
			<div class="card">
				<div class="card-body">
					<div class="d-flex align-items-start justify-content-between">
						<div class="content-left">
							<span>{{ __('Users') }}</span>
							<div class="d-flex align-items-end mt-2">
								<h3 class="mb-0 me-2">{{ $totalUser }}</h3>
								<small class="text-success">(100%)</small>
							</div>
							<small>{{ __('Total Users') }}</small>
						</div>
						<span class="badge bg-label-primary rounded p-2">
							<i class="ti ti-user ti-sm"></i>
						</span>
					</div>
				</div>
			</div>
		</div>
		<div class="col-sm-6 col-xl-3">
			<div class="card">
				<div class="card-body">
					<div class="d-flex align-items-start justify-content-between">
						<div class="content-left">
							<span>{{ __('Verified Users') }}</span>
							<div class="d-flex align-items-end mt-2">
								<h3 class="mb-0 me-2">{{ $verified }}</h3>
								<small class="text-success">({{ $totalUser > 0 ? round(($verified / $totalUser) * 100) : 0 }}%)</small>
							</div>
							<small>{{ __('Email verified') }}</small>
						</div>
						<span class="badge bg-label-success rounded p-2">
							<i class="ti ti-user-check ti-sm"></i>
						</span>
					</div>
				</div>
			</div>
		</div>
		<div class="col-sm-6 col-xl-3">
			<div class="card">
				<div class="card-body">
					<div class="d-flex align-items-start justify-content-between">
						<div class="content-left">
							<span>{{ __('Duplicate Users') }}</span>
							<div class="d-flex align-items-end mt-2">
								<h3 class="mb-0 me-2">{{ $userDuplicates }}</h3>
								<small class="text-warning">({{ $totalUser > 0 ? round(($userDuplicates / $totalUser) * 100) : 0 }}%)</small>
							</div>
							<small>{{ __('Duplicate emails') }}</small>
						</div>
						<span class="badge bg-label-danger rounded p-2">
							<i class="ti ti-users ti-sm"></i>
						</span>
					</div>
				</div>
			</div>
		</div>
		<div class="col-sm-6 col-xl-3">
			<div class="card">
				<div class="card-body">
					<div class="d-flex align-items-start justify-content-between">
						<div class="content-left">
							<span>{{ __('Verification Pending') }}</span>
							<div class="d-flex align-items-end mt-2">
								<h3 class="mb-0 me-2">{{ $notVerified }}</h3>
								<small class="text-danger">({{ $totalUser > 0 ? round(($notVerified / $totalUser) * 100) : 0 }}%)</small>
							</div>
							<small>{{ __('Pending verification') }}</small>
						</div>
						<span class="badge bg-label-warning rounded p-2">
							<i class="ti ti-user-circle ti-sm"></i>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Users DataTable -->
	<div class="card">
		<div class="card-body">
			{{ $dataTable->table() }}
		</div>
	</div>
@endsection

@push('scripts')
	{{ $dataTable->scripts(attributes: ['type' => 'module']) }}

	<script>
		function deleteRecord(id, element) {
			event.preventDefault();
			Swal.fire({
				title: '{{ __("Are you sure?") }}',
				text: "{{ __('Do you want to remove this user from the team?') }}",
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: '{{ __("Yes, remove") }}',
				cancelButtonText: '{{ __("Cancel") }}',
				customClass: {
					confirmButton: 'btn btn-primary me-3',
					cancelButton: 'btn btn-label-secondary'
				},
				buttonsStyling: false
			}).then(function (result) {
				if (result.value) {
					fetch("{{ route('user.destroy', ['id' => ':ID']) }}".replace(':ID', id), {
						method: 'DELETE',
						headers: {
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': '{{ csrf_token() }}'
						}
					})
					.then(response => response.json())
					.then(data => {
						if (data.error) {
							Swal.fire({
								icon: 'error',
								title: '{{ __("Error") }}',
								text: data.error,
								customClass: {
									confirmButton: 'btn btn-primary'
								}
							});
						} else {
							const row = element.closest('tr');
							if (row) {
								row.classList.add('fade-out');
								row.addEventListener('transitionend', () => {
									row.remove();
								});
							}

							Swal.fire({
								icon: 'success',
								title: '{{ __("Success!") }}',
								text: data.success,
								customClass: {
									confirmButton: 'btn btn-success'
								}
							});
						}
					})
					.catch(error => {
						console.error('Error:', error);
						Swal.fire({
							icon: 'error',
							title: '{{ __("Error") }}',
							text: '{{ __("An error occurred while processing the request") }}',
							customClass: {
								confirmButton: 'btn btn-primary'
							}
						});
					});
				}
			});
		}
	</script>
@endpush
