@extends('layouts/layoutMaster')

@section('title', 'Employees')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/form-layouts.js')}}"></script>
<script>
	$(function() {
		// Initialize Select2 if available
		if ($.fn.select2) {
			$('#language, #responsible_id, #status_id').select2();
		}
	});
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">Employees/</span> {{ isset($contact->id) ? 'Edit' : 'Create' }}</h4>
		<p class="text-muted">Manage employee information</p>
	</div>
</div>

<div class="card mb-4">
	<h5 class="card-header">Employee Information</h5>
	<form class="card-body" action="{{ isset($contact) ? route('employee.update', $contact->id) : route('employee.store') }}" method="POST">
		@csrf
		@if(isset($contact))
			<input type="hidden" name="_method" value="PUT">
		@endif

		<div class="row g-3">
			<!-- Personal Information -->
			<div class="col-md-6">
				<x-input-general id="name" label="Name (*)" value="{{ old('name', $contact->name ?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-general id="surname" label="Surname" value="{{ old('surname', $contact->surname ?? '') }}" />
			</div>

			<div class="col-md-6">
				<x-input-general id="email" label="Email (*)" type="email" value="{{ old('email', $contact->email ?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-general id="phone" label="Phone" value="{{ old('phone', $contact->phone ?? '') }}" />
			</div>

			<div class="col-md-6">
				<x-input-date id="birthday" label="Birthday" value="{{ old('birthday', $contact->birthday ?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-select id="language" label="Language (*)" :options="$languages" value="{{ old('language', $contact->language ?? '') }}" />
			</div>

			<!-- Employee Specific Fields -->
			<div class="col-md-6">
				<x-input-general id="dni" label="DNI" value="{{ old('dni', $contact->data->dni ?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-general id="nationality" label="Nationality" value="{{ old('nationality', $contact->data->nationality ?? '') }}" />
			</div>

			<div class="col-md-6">
				<x-input-general id="naf" label="NAF" value="{{ old('naf', $contact->data->naf ?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-general id="command" label="Command" value="{{ old('command', $contact->data->command ?? '') }}" />
			</div>

			<div class="col-md-6">
				<x-input-general id="city" label="City" value="{{ old('city', $contact->data->city ?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-general id="province" label="Province" value="{{ old('province', $contact->data->province ?? '') }}" />
			</div>

			<div class="col-md-12">
				<x-input-general id="address" label="Address" value="{{ old('address', $contact->data->address ?? '') }}" />
			</div>

			<div class="col-md-6">
				<x-input-general id="postal_code" label="Postal Code" value="{{ old('postal_code', $contact->data->postal_code ?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-general id="contract_type" label="Contract Type" value="{{ old('contract_type', $contact->data->contract_type ?? '') }}" />
			</div>

			<div class="col-md-6">
				<x-input-general id="account_number" label="Account Number" value="{{ old('account_number', $contact->data->account_number ?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-select id="status_id" label="Status (*)" :options="$statuses" value="{{ old('status_id', $contact->status_id ?? '1') }}" />
			</div>

			<div class="col-md-6">
				<x-input-select id="responsible_id" label="Responsible" :options="$users" value="{{ old('responsible_id', $contact->responsible_id ?? '') }}" />
			</div>
			<div class="col-md-6">
				<div class="form-check form-switch">
					<input class="form-check-input" type="checkbox" id="active" name="active" value="1" {{ old('active', $contact->data->active ?? true) ? 'checked' : '' }}>
					<label class="form-check-label" for="active">Active</label>
				</div>
			</div>

			<div class="col-md-12">
				<x-input-textarea id="profile" label="Profile" value="{{ old('profile', $contact->profile ?? '') }}" />
			</div>
		</div>

		<div class="pt-4">
			<div class="col-12 d-flex">
				<button type="submit" class="btn btn-primary me-sm-3 me-1">Save</button>
				<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('employee.index') }}'">Cancel</button>
			</div>
		</div>
	</form>
</div>
@endsection
