@extends('layouts/layoutMaster')

@section('title', __('Users'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Users') }}/</span> {{ $user->name }}</h4>
		<p class="text-muted">{{ __('User details and information') }}</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3">
		@can('user.edit')
		<a href="{{ route('user.edit', $user->id) }}" class="btn btn-primary waves-effect waves-light">
			<i class="ti ti-edit me-1"></i>{{ __('Edit User') }}
		</a>
		@endcan
		@can('user.index')
		<a href="{{ route('user.index') }}" class="btn btn-outline-secondary waves-effect waves-light">
			<i class="ti ti-arrow-left me-1"></i>{{ __('Back to Users') }}
		</a>
		@endcan
	</div>
</div>

<div class="row">
	<div class="col-md-6">
		<!-- User Information -->
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="card-title mb-0">{{ __('User Information') }}</h5>
			</div>
			<div class="card-body">
				<div class="row">
					<div class="col-12 mb-3">
						<label class="form-label">{{ __('Name') }}</label>
						<p class="form-control-static">{{ $user->name }}</p>
					</div>
					<div class="col-12 mb-3">
						<label class="form-label">{{ __('Email') }}</label>
						<p class="form-control-static">{{ $user->email }}</p>
					</div>
					<div class="col-12 mb-3">
						<label class="form-label">{{ __('Email Verification') }}</label>
						<p class="form-control-static">
							@if ($user->email_verified_at)
								<span class="badge bg-label-success">{{ __('Verified') }}</span>
								<small class="text-muted d-block">{{ $user->email_verified_at->format('d/m/Y H:i') }}</small>
							@else
								<span class="badge bg-label-warning">{{ __('Pending') }}</span>
							@endif
						</p>
					</div>
					<div class="col-12 mb-3">
						<label class="form-label">{{ __('Roles') }}</label>
						<p class="form-control-static">
							@forelse($user->roles as $role)
								<span class="badge bg-label-primary me-1">{{ ucfirst($role->name) }}</span>
							@empty
								<span class="text-muted">{{ __('No roles assigned') }}</span>
							@endforelse
						</p>
					</div>
					<div class="col-12 mb-3">
						<label class="form-label">{{ __('Account Created') }}</label>
						<p class="form-control-static">{{ $user->created_at->format('d/m/Y H:i') }}</p>
					</div>
					<div class="col-12 mb-3">
						<label class="form-label">{{ __('Last Updated') }}</label>
						<p class="form-control-static">{{ $user->updated_at->format('d/m/Y H:i') }}</p>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-md-6">
		<!-- Teams Information -->
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="card-title mb-0">{{ __('Teams') }}</h5>
			</div>
			<div class="card-body">
				@forelse($user->teams as $team)
					<div class="d-flex align-items-center mb-2">
						<div class="avatar me-2">
							<span class="avatar-initial rounded bg-label-primary">
								<i class="ti ti-users ti-sm"></i>
							</span>
						</div>
						<div>
							<h6 class="mb-0">{{ $team->name }}</h6>
							<small class="text-muted">{{ __('Member since') }}: {{ $team->pivot->created_at ? $team->pivot->created_at->format('d/m/Y') : __('N/A') }}</small>
						</div>
					</div>
				@empty
					<p class="text-muted">{{ __('Not a member of any teams') }}</p>
				@endforelse
			</div>
		</div>

		<!-- Activity Information -->
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="card-title mb-0">{{ __('Activity') }}</h5>
			</div>
			<div class="card-body">
				<div class="row">
					<div class="col-12 mb-3">
						<label class="form-label">{{ __('Profile Photo') }}</label>
						<div class="d-flex align-items-center">
							@if($user->profile_photo_path)
								<img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" class="rounded-circle me-2" width="40" height="40">
							@else
								<div class="avatar me-2">
									<span class="avatar-initial rounded-circle bg-label-primary">
										{{ strtoupper(substr($user->name, 0, 2)) }}
									</span>
								</div>
							@endif
							<span>{{ $user->name }}</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
