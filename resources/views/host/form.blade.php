@extends('layouts/layoutMaster')

@section('title', ' Hosts')

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
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">Hosts/</span> {{ isset($data) ? 'Edit' : 'Create' }}</h4>
        <p class="text-muted">Monitor and Optimize Your Network Devices and Hosts</p>
    </div>
</div>

<div class="card mb-4">
	<h5 class="card-header">Hosts</h5>
	<form class="card-body" action="{{ route('host.store') }}" method="POST">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">
		
		<div class="row g-3">
			<div class="col-md-6">
				<x-input-general id="name" label="Name (*)" value="{{ old('name', $data->name?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-select-array id="type_id" label="Type" :options="$data->types" value="{{ old('type_id', $data->type_id ?? '') }}" />
			</div>
			
			<div class="col-md-6">
				<x-input-general id="user" label="User" value="{{ old('user', $data->user?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-general id="password" label="Password" value="{{ old('password', $data->password?? '') }}" />
			</div>

			<div class="col-md-6">
				<x-input-general id="private_ip" label="Private IP" value="{{ old('private_ip', $data->private_ip?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-select-array id="private_connection_id" label="Connection" :options="$data->devices" value="{{ old('private_connection_id', $data->private_connection_id ?? '') }}" />
			</div>
			
			<div class="col-md-6">
				<x-input-general id="public_ip" label="Public IP" value="{{ old('public_ip', $data->public_ip?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-select-array id="public_connection_id" label="Connection" :options="$data->devices" value="{{ old('public_connection_id', $data->public_connection_id ?? '') }}" />
			</div>
		</div>
		<hr class="my-4 mx-n4" />

		<div class="pt-4">
			<button type="submit" class="btn btn-primary me-sm-3 me-1">Send</button>
			<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('host.index') }}'">Cancel</button>
		</div>
	</form>
</div>
@endsection