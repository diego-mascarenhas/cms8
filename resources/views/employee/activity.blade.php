@extends('layouts/layoutMaster')

@section('title', 'Employee Activity')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">Employee/</span> {{ $contact->name }}</h4>
		<p class="text-muted">Activity log</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3">
		<a href="{{ route('employee.show', $contact->id) }}" class="btn btn-primary waves-effect waves-light">
			<i class="ti ti-arrow-left me-1"></i>Back to Employee
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
						src="{{ \App\Helpers\AvatarHelper::generate($contact->name, 100) }}" height="100"
						width="100" alt="Employee avatar" />
					<div class="user-info text-center">
						<h4 class="mb-2">{{ $contact->name }}</h4>
						@if($contact->data->command ?? false)
							<span class="badge bg-label-secondary mt-1">#{{ $contact->data->command }}</span>
						@endif
					</div>
				</div>
				<div class="mt-4">
					<ul class="list-unstyled">
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">Email:</span>
							<span>{{ $contact->email }}</span>
						</li>
						@if($contact->phone)
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
					</ul>
				</div>
			</div>
		</div>
	</div>

	<!-- Activity Content -->
	<div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
		<div class="card">
			<div class="card-header">
				<h5 class="card-title mb-0">Activity Log</h5>
			</div>
			<div class="card-body">
				@if($activities->count() > 0)
					<div class="timeline">
						@foreach($activities as $activity)
							<div class="timeline-item timeline-item-transparent">
								<span class="timeline-point timeline-point-primary"></span>
								<div class="timeline-event">
									<div class="timeline-header mb-1">
										<h6 class="mb-0">{{ ucfirst($activity->description) }}</h6>
										<small class="text-muted">{{ $activity->created_at->format('d/m/Y H:i') }}</small>
									</div>
									@if($activity->changes->count() > 0)
										<div class="timeline-body">
											<small class="text-muted">
												@foreach($activity->changes as $field => $change)
													@if($field !== 'updated_at')
														<strong>{{ ucfirst(str_replace('_', ' ', $field)) }}:</strong>
														@if(is_array($change))
															{{ $change['old'] ?? 'null' }} → {{ $change['new'] ?? 'null' }}
														@else
															{{ $change }}
														@endif
														@if(!$loop->last), @endif
													@endif
												@endforeach
											</small>
										</div>
									@endif
								</div>
							</div>
						@endforeach
					</div>

					<div class="d-flex justify-content-center mt-4">
						{{ $activities->links() }}
					</div>
				@else
					<p class="text-muted text-center">No activity recorded yet.</p>
				@endif
			</div>
		</div>
	</div>
</div>
@endsection
