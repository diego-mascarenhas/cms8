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
			$('#role_id').select2();
		}
	});
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Users') }}/</span> {{ isset($data->id) ? __('Edit') : __('Create') }}</h4>
		<p class="text-muted">{{ __('Manage team users') }}</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3">
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
				<input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" />
				@error('password')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>

			<div class="col-md-6">
				<label for="password_confirmation" class="form-label">{{ __('Confirm Password') }}</label>
				<input type="password" class="form-control @error('password_confirmation') is-invalid @enderror" id="password_confirmation" name="password_confirmation" />
				@error('password_confirmation')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>

			<div class="col-md-6">
				<label for="role_id" class="form-label">{{ __('Role') }} (*)</label>
				<select class="form-select @error('role_id') is-invalid @enderror" id="role_id" name="role_id">
					<option value="">{{ __('Select a role') }}</option>
					@foreach($roles as $role)
						<option value="{{ $role->id }}"
							@if(old('role_id', (isset($data) && $data->roles->first()) ? $data->roles->first()->id : '') == $role->id) selected @endif>
							{{ ucfirst($role->name) }}
						</option>
					@endforeach
				</select>
				@error('role_id')
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
