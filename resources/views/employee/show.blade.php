@extends('layouts/layoutMaster')

@section('title', 'Employee Details')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-user-view.css')}}" />
<style>
	.tab-content {
		padding: 0 !important;
		background: transparent !important;
	}
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/app-user-view.js')}}"></script>
<script src="{{asset('assets/js/app-user-view-account.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">Employee/</span> {{ $contact->name }}</h4>
		<p class="text-muted">
			Created on {{ Carbon\Carbon::parse($contact->created_at)->isoFormat('D [de] MMMM [de] YYYY, HH:mm [hs]') }}</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3">
		<a href="{{ route('employee.edit', $contact->id) }}" class="btn btn-primary waves-effect waves-light"><i
				class="ti ti-edit me-1"></i>Edit Employee</a>
		<a href="{{ route('employee.absences', $contact->id) }}" class="btn btn-info waves-effect waves-light"><i
				class="ti ti-calendar me-1"></i>Availability</a>
	</div>
</div>

<div class="row">
	<!-- Employee Sidebar -->
	<div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
		<!-- Employee Card -->
		<div class="card mb-4">
			<div class="card-body">
				<div class="user-avatar-section">
					<div class=" d-flex align-items-center flex-column">
						<img class="img-fluid rounded mb-3 pt-1 mt-4"
							src="https://ui-avatars.com/api/?format=svg&name={{ $contact->name }}" height="100"
							width="100" alt="Employee avatar" />
						<div class="user-info text-center">
							<h4 class="mb-2">{{ $contact->name }}</h4>
							@if($contact->data->command ?? false)
								<span class="badge bg-label-secondary mt-1">#{{ $contact->data->command }}</span>
							@endif
						</div>
					</div>
				</div>
				<div class="d-flex justify-content-start flex-wrap mt-3 pt-3 pb-4 border-bottom">
					<div class="d-flex align-items-start me-4 mt-3 gap-2">
						<span class="badge bg-label-primary p-2 rounded">
							<i class='ti ti-user-plus ti-sm'></i>
						</span>
						<div>
							<p class="mb-0 fw-medium" style="line-height: 1.2;">
								{{ Carbon\Carbon::parse($contact->updated_at)->format('d/m/Y') }}</p>
							<small style="line-height: 1.2;">Last update</small>
						</div>
					</div>
				</div>
				<div class="mt-4 info-container">
					<ul class="list-unstyled">
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">Status:</span>
							<span class="badge {{ $contact->status->label_class }}">{{ $contact->status->name }}</span>
						</li>
						@if ($contact->email)
							<li class="mb-2 pt-1">
								<span class="fw-medium me-1">Email:</span>
								<span>{{ $contact->email }}</span>
							</li>
						@endif
						@if ($contact->phone)
							<li class="mb-2 pt-1">
								<span class="fw-medium me-1">Phone:</span>
								<span>{{ $contact->phone }}</span>
							</li>
						@endif
						@if($contact->data->city ?? false)
							<li class="mb-2 pt-1">
								<span class="fw-medium me-1">City:</span>
								<span>{{ $contact->data->city }}</span>
							</li>
						@endif
						@if($contact->data->province ?? false)
							<li class="mb-2 pt-1">
								<span class="fw-medium me-1">Province:</span>
								<span>{{ $contact->data->province }}</span>
							</li>
						@endif
						@if($contact->data->dni ?? false)
							<li class="mb-2 pt-1">
								<span class="fw-medium me-1">DNI:</span>
								<span>{{ $contact->data->dni }}</span>
							</li>
						@endif
						@if($contact->data->nationality ?? false)
							<li class="mb-2 pt-1">
								<span class="fw-medium me-1">Nationality:</span>
								<span>{{ $contact->data->nationality }}</span>
							</li>
						@endif
						@if($contact->data->naf ?? false)
							<li class="mb-2 pt-1">
								<span class="fw-medium me-1">NAF:</span>
								<span>{{ $contact->data->naf }}</span>
							</li>
						@endif
						@if($contact->data->contract_type ?? false)
							<li class="mb-2 pt-1">
								<span class="fw-medium me-1">Contract Type:</span>
								<span>{{ $contact->data->contract_type }}</span>
							</li>
						@endif
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">Active:</span>
							@if($contact->data->active ?? true)
								<span class="badge bg-label-success">Active</span>
							@else
								<span class="badge bg-label-danger">Inactive</span>
							@endif
						</li>
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">Responsible:</span>
							<span>{{ $contact->responsible->name ?? 'Not assigned' }}</span>
						</li>
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">Birthday:</span>
							<span>
								@if (isset($contact->birthday))
									{{ \Carbon\Carbon::parse($contact->birthday)->format('d/m/Y') }}
									({{ \Carbon\Carbon::parse($contact->birthday)->age }} years)
								@else
									Not available
								@endif
							</span>
						</li>
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">Created by:</span>
							<span>{{ $contact->creator->name ?? 'Not assigned' }}</span>
						</li>
					</ul>
				</div>
			</div>
		</div>
	</div>

	<!-- Employee Content -->
	<div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
		<!-- Employee Pills -->
		<ul class="nav nav-pills flex-column flex-md-row mb-4" id="myTab" role="tablist">
			<li class="nav-item" role="presentation">
				<a class="nav-link active" id="general-tab" data-bs-toggle="tab" href="#general" role="tab"
					aria-controls="general" aria-selected="true">
					<i class="ti ti-user ti-xs me-1"></i>General
				</a>
			</li>
			<li class="nav-item" role="presentation">
				<a class="nav-link" id="availability-tab" data-bs-toggle="tab" href="#availability" role="tab"
					aria-controls="availability" aria-selected="false">
					<i class="ti ti-calendar ti-xs me-1"></i>Availability
				</a>
			</li>
			<li class="nav-item" role="presentation">
				<a class="nav-link" id="activity-tab" data-bs-toggle="tab" href="#activity" role="tab"
					aria-controls="activity" aria-selected="false">
					<i class="ti ti-activity ti-xs me-1"></i>Activity
				</a>
			</li>
		</ul>

		<div class="tab-content">
			<div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
				@include('employee.partials.general')
			</div>
			<div class="tab-pane fade" id="availability" role="tabpanel" aria-labelledby="availability-tab">
				@include('employee.partials.availability')
			</div>
			<div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
				@include('employee.partials.activity')
			</div>
		</div>
	</div>
</div>
@endsection
