@extends('layouts/layoutMaster')

@section('title', __('Dashboard'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/apex-charts/apex-charts.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/apex-charts/apexcharts.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/dashboards-analytics.js')}}"></script>
@endsection

@section('content')
<div class="row">
	<div class="col-12">
		<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
			<div class="d-flex flex-column justify-content-center">
				<h4 class="mb-1 mt-3">{{ __('Welcome back') }}, {{ $user->name }}! 👋</h4>
				<p class="text-muted">{{ __('Your team dashboard overview') }}</p>
			</div>
		</div>
	</div>
</div>

<!-- Team Statistics -->
<div class="row">
	<!-- Contacts -->
	<div class="col-lg-3 col-md-6 col-sm-12 mb-4">
		<div class="card">
			<div class="card-body">
				<div class="d-flex align-items-start justify-content-between">
					<div class="content-left">
						<span class="text-heading">{{ __('Total Contacts') }}</span>
						<div class="d-flex align-items-center my-2">
							<h3 class="mb-0 me-2">{{ number_format($teamStats['total_contacts']) }}</h3>
							<small class="text-success">👥</small>
						</div>
						<p class="mb-0">{{ __('CRM contacts database') }}</p>
					</div>
					<div class="avatar">
						<span class="avatar-initial rounded bg-label-primary">
							<i class="ti ti-users ti-sm">👥</i>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Projects -->
	<div class="col-lg-3 col-md-6 col-sm-12 mb-4">
		<div class="card">
			<div class="card-body">
				<div class="d-flex align-items-start justify-content-between">
					<div class="content-left">
						<span class="text-heading">{{ __('Active Projects') }}</span>
						<div class="d-flex align-items-center my-2">
							<h3 class="mb-0 me-2">{{ $teamStats['active_projects'] }}</h3>
							<small class="text-success">📂</small>
						</div>
						<p class="mb-0">{{ __('Currently running') }}</p>
					</div>
					<div class="avatar">
						<span class="avatar-initial rounded bg-label-success">
							<i class="ti ti-folders ti-sm">📂</i>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Tasks -->
	<div class="col-lg-3 col-md-6 col-sm-12 mb-4">
		<div class="card">
			<div class="card-body">
				<div class="d-flex align-items-start justify-content-between">
					<div class="content-left">
						<span class="text-heading">{{ __('Pending Tasks') }}</span>
						<div class="d-flex align-items-center my-2">
							<h3 class="mb-0 me-2">{{ $teamStats['pending_tasks'] }}</h3>
							<small class="text-warning">⏳</small>
						</div>
						<p class="mb-0">{{ __('Tasks to complete') }}</p>
					</div>
					<div class="avatar">
						<span class="avatar-initial rounded bg-label-warning">
							<i class="ti ti-checklist ti-sm">✅</i>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Revenue -->
	<div class="col-lg-3 col-md-6 col-sm-12 mb-4">
		<div class="card">
			<div class="card-body">
				<div class="d-flex align-items-start justify-content-between">
					<div class="content-left">
						<span class="text-heading">{{ __('Monthly Revenue') }}</span>
						<div class="d-flex align-items-center my-2">
							<h3 class="mb-0 me-2">${{ number_format($teamStats['monthly_revenue']) }}</h3>
							<small class="text-success">💰</small>
						</div>
						<p class="mb-0">{{ __('This month earnings') }}</p>
					</div>
					<div class="avatar">
						<span class="avatar-initial rounded bg-label-info">
							<i class="ti ti-receipt ti-sm">🧾</i>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Module Status -->
<div class="row">
	<div class="col-12">
		<div class="card mb-4">
			<h5 class="card-header">{{ __('Module Status') }}</h5>
			<div class="card-body">
				<div class="row">
					@foreach($moduleStatus as $key => $module)
					<div class="col-lg-3 col-md-6 col-sm-12 mb-4">
						<div class="d-flex align-items-center">
							<div class="avatar me-3">
								<span class="avatar-initial rounded bg-label-{{ $module['enabled'] ? 'success' : 'secondary' }}">
									<i class="{{ $module['icon'] }} ti-sm"></i>
								</span>
							</div>
							<div>
								<h6 class="mb-0">{{ $module['name'] }}</h6>
								<small class="text-{{ $module['enabled'] ? 'success' : 'muted' }}">
									{{ $module['enabled'] ? __('Enabled') : __('Disabled') }}
								</small>
							</div>
						</div>
					</div>
					@endforeach
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Recent Activities -->
<div class="row">
	<div class="col-12">
		<div class="card">
			<h5 class="card-header">{{ __('Recent Activities') }}</h5>
			<div class="card-body">
				@if($recentActivities->count() > 0)
					<div class="timeline timeline-center">
						@foreach($recentActivities as $activity)
						<div class="timeline-item">
							<span class="timeline-indicator timeline-indicator-primary">
								<i class="ti ti-circle-filled"></i>
							</span>
							<div class="timeline-event">
								<div class="timeline-header">
									<h6 class="mb-0">{{ $activity->description }}</h6>
									<small class="text-muted">{{ $activity->created_at->diffForHumans() }}</small>
								</div>
								@if($activity->causer)
								<p class="mb-0">{{ __('by') }} {{ $activity->causer->name }}</p>
								@endif
							</div>
						</div>
						@endforeach
					</div>
				@else
					<div class="text-center py-4">
						<i class="ti ti-activity ti-3x text-muted mb-3"></i>
						<h5>{{ __('No activities yet') }}</h5>
						<p class="text-muted">{{ __('Activities will appear here once your team starts using the system.') }}</p>
					</div>
				@endif
			</div>
		</div>
	</div>
</div>
@endsection
