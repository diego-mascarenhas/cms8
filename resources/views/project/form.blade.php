@extends('layouts/layoutMaster')

@section('title', ' Projects')

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
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">Projects/</span> {{ isset($data) ? 'Edit' : 'Create' }}</h4>
        <p class="text-muted">Track your projects</p>
    </div>
</div>

<div class="card mb-4">
	<h5 class="card-header">Project</h5>
	<form class="card-body" action="{{ route('project.store') }}" method="POST">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">
		
		<div class="row g-3">
			<div class="col-md-6">
				<x-input-general id="name" label="Name (*)" value="{{ old('name', $data->name?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-select-array id="type_id" label="Category" :options="$data->types" value="{{ old('type_id', $data->type_id ?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-general id="name" label="Name (*)" value="{{ old('name', $data->name?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-general id="name" label="Name (*)" value="{{ old('name', $data->name?? '') }}" />
			</div>
			<div class="col-md-12">
				<x-input-textarea id="text" label="Description (*)" value="{{ old('description', $data->description?? '') }}" />
			</div>
			<div class="col-xl-12 p-4">
				<div class="text-light small fw-medium">Status</div>
				<div class="demo-inline-spacing">
					<x-input-checkbox name="status" label="Active" value="{{ old('status', $data->status ?? '') }}" />
				</div>
			</div>
		</div>
		<hr class="my-4 mx-n4" />

		<div class="pt-4">
			<button type="submit" class="btn btn-primary me-sm-3 me-1">Send</button>
			<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('app-mkt-message-list') }}'">Cancel</button>
		</div>
	</form>
</div>
@endsection