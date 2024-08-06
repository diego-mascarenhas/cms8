@extends('layouts/layoutMaster')

@section('title', ' Suppliers')

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
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">Suppliers/</span> {{ isset($data->id) ? 'Edit' : 'Create' }}</h4>
        <p class="text-muted">Manage and oversee your suppliers to ensure efficient procurement and supplier relationships.</p>
    </div>
    <!-- <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('category.create') }}" type="submit" class="btn btn-primary waves-effect waves-light">Eliminar</a>
    </div> -->
</div>

<div class="card mb-4">
	<h5 class="card-header">Suppliers</h5>
	<form class="card-body" action="{{ route('supplier.store') }}" method="POST">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">
		
		<div class="row g-3">
			<div class="col-md-4">
				<x-input-general id="name" label="Name (*)" value="{{ old('name', $data->name?? '') }}" />
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
			<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('app-supplier-list') }}'">Cancel</button>
		</div>
	</form>
</div>
@endsection