@extends('layouts/layoutMaster')

@section('title', 'Employees')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/delete.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3">Employees</h4>
		<p class="text-muted">Manage your employees</p>
	</div>
	@can('employee.create')
	<div class="mt-3 mt-md-0">
		<a href="{{ route('employee.create') }}" class="btn btn-primary"> <i class="ti ti-plus me-1"></i> Add Employee </a>
	</div>
	@endcan
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
	<div class="col-lg-3 col-sm-6 mb-4">
		<div class="card">
			<div class="card-body">
				<div class="d-flex justify-content-between">
					<div class="card-info">
						<p class="card-text">Total Employees</p>
						<div class="d-flex align-items-end mb-2">
							<h4 class="card-title mb-0">{{ $dashboardStats['totalEmployees'] }}</h4>
						</div>
					</div>
					<div class="card-icon">
						<span class="badge bg-label-primary rounded p-2">
							<i class="ti ti-users ti-sm"></i>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="col-lg-3 col-sm-6 mb-4">
		<div class="card">
			<div class="card-body">
				<div class="d-flex justify-content-between">
					<div class="card-info">
						<p class="card-text">Active Employees</p>
						<div class="d-flex align-items-end mb-2">
							<h4 class="card-title mb-0">{{ $dashboardStats['activeEmployees'] }}</h4>
						</div>
					</div>
					<div class="card-icon">
						<span class="badge bg-label-success rounded p-2">
							<i class="ti ti-user-check ti-sm"></i>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="col-lg-3 col-sm-6 mb-4">
		<div class="card">
			<div class="card-body">
				<div class="d-flex justify-content-between">
					<div class="card-info">
						<p class="card-text">New This Week</p>
						<div class="d-flex align-items-end mb-2">
							<h4 class="card-title mb-0">{{ $dashboardStats['newThisWeek'] }}</h4>
						</div>
					</div>
					<div class="card-icon">
						<span class="badge bg-label-info rounded p-2">
							<i class="ti ti-user-plus ti-sm"></i>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Employees Table -->
<div class="card">
	<div class="card-datatable table-responsive">
		{{ $dataTable->table() }}
	</div>
</div>
@endsection

@push('scripts')
{{ $dataTable->scripts() }}
@endpush
