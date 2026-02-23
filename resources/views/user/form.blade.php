@extends('layouts/layoutMaster')

@section('title', __('Users'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('page-script')
<script>
	$(function() {
		// Initialize Select2 if available
		if ($.fn.select2) {
			$('#role_ids').select2({
				placeholder: '{{ __("Select roles") }}',
				allowClear: true
			});
		}
		// Toggle password visibility (eye icon)
		document.querySelectorAll('.toggle-password').forEach(function(toggle) {
			toggle.addEventListener('click', function(e) {
				var group = e.currentTarget.closest('.input-group');
				var input = group.querySelector('input');
				var icon = group.querySelector('i');
				if (input.type === 'password') {
					input.type = 'text';
					icon.classList.remove('ti-eye-off');
					icon.classList.add('ti-eye');
				} else {
					input.type = 'password';
					icon.classList.remove('ti-eye');
					icon.classList.add('ti-eye-off');
				}
			});
		});
	});
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Users') }}/</span> {{ isset($data->id) ? __('Edit') : __('Create') }}</h4>
		<p class="text-muted">{{ __('Manage team users and their permissions') }}</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3">
		@if(isset($data->id) && auth()->user()->currentTeam && (Gate::check('addTeamMember', auth()->user()->currentTeam) || Gate::check('updateTeamMember', auth()->user()->currentTeam)))
			<a href="{{ route('teams.show', auth()->user()->currentTeam) }}#team-members" class="btn btn-primary waves-effect waves-light">
				<i class="ti ti-users me-1"></i>{{ __('Manage user') }}
			</a>
		@endif
		@can('user.index')
			<a href="{{ route('user.index') }}" class="btn btn-outline-secondary waves-effect waves-light">
				<i class="ti ti-arrow-left me-1"></i>{{ __('Back to Users') }}
			</a>
		@endcan
	</div>
</div>

<div class="card mb-4">
	<h5 class="card-header">{{ isset($data->id) ? __('Edit User') : __('Create User') }}</h5>
	<form class="card-body" action="{{ isset($data->id) ? route('user.update', $data->id) : route('user.store') }}" method="POST">
		@csrf
		@if(isset($data->id))
			@method('PUT')
		@endif

		<div class="row g-3">
			<div class="col-md-6">
				<x-input-general id="name" label="{{ __('Name') }} (*)" value="{{ old('name', $data->name ?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-general id="email" label="{{ __('Email') }} (*)" type="email" value="{{ old('email', $data->email ?? '') }}" />
			</div>

			<div class="col-md-6">
				<label for="password" class="form-label">{{ __('Password') }} {{ !isset($data->id) ? '(*)' : '(' . __('leave blank to keep current') . ')' }}</label>
				<div class="input-group input-group-merge">
					<input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" />
					<span class="input-group-text cursor-pointer toggle-password" title="{{ __('Show password') }}"><i class="ti ti-eye-off"></i></span>
				</div>
				@error('password')
					<div class="invalid-feedback d-block">{{ $message }}</div>
				@enderror
			</div>

			<div class="col-md-6">
				<label for="password_confirmation" class="form-label">{{ __('Confirm Password') }}</label>
				<div class="input-group input-group-merge">
					<input type="password" class="form-control @error('password_confirmation') is-invalid @enderror" id="password_confirmation" name="password_confirmation" />
					<span class="input-group-text cursor-pointer toggle-password" title="{{ __('Show password') }}"><i class="ti ti-eye-off"></i></span>
				</div>
				@error('password_confirmation')
					<div class="invalid-feedback d-block">{{ $message }}</div>
				@enderror
			</div>

			<div class="col-md-6 d-none">
				<label for="role_ids" class="form-label">{{ __('Roles') }} (*)</label>
				<select class="form-select @error('role_ids') is-invalid @enderror" id="role_ids" name="role_ids[]" multiple>
					@foreach($roles as $role)
						<option value="{{ $role->id }}"
							@if(in_array($role->id, old('role_ids', isset($data) ? $data->roles->pluck('id')->toArray() : ($roles->isNotEmpty() ? [$roles->first()->id] : [])))) selected @endif>
							{{ ucfirst($role->name) }}
						</option>
					@endforeach
				</select>
				<div class="form-text">{{ __('Hold Ctrl/Cmd to select multiple roles') }}</div>
				@error('role_ids')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>
		</div>

		<div class="pt-4">
			<div class="col-12 d-flex">
				<button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Save') }}</button>
				<button type="button" class="btn btn-label-secondary" onclick="location.href='{{ route('user.index') }}'">{{ __('Cancel') }}</button>
			</div>
		</div>
	</form>
</div>
@endsection
