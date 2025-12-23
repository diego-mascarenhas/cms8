@extends('layouts/layoutMaster')

@section('title', 'Templates')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/form-layouts.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">Templates/</span> {{ isset($data->id) ? 'Edit' : 'Create' }}</h4>
        <p class="text-muted">Create and manage email templates with visual editor</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @if(isset($data->id))
            <a href="{{ route('template.editor', $data->getHashedId()) }}" class="btn btn-info waves-effect waves-light">
                <i class="ti ti-edit me-1"></i>Visual Editor
            </a>
        @endif
    </div>
</div>

<div class="card mb-4">
	<h5 class="card-header">Template Information</h5>
	<form class="card-body" action="{{ route('template.store') }}" method="POST">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">
		
		<div class="row g-3">
			<div class="col-md-8">
				<x-input-general id="name" label="Template Name (*)" value="{{ old('name', $data->name ?? '') }}" />
			</div>
			<div class="col-md-4">
				<div class="form-check form-switch mt-4">
					<input class="form-check-input" type="checkbox" id="status_id" name="status_id" value="1" {{ old('status_id', $data->status_id ?? 1) == 1 ? 'checked' : '' }}>
					<label class="form-check-label" for="status_id">
						<strong>Active Template</strong>
					</label>
				</div>
			</div>
		</div>
		
		@if(isset($data->id))
		<div class="row g-3 mt-3">
			<div class="col-12">
				<div class="alert alert-info">
					<i class="ti ti-info-circle me-2"></i>
					<strong>Template Created!</strong> Use the Visual Editor button above to design your email template with GrapesJS.
				</div>
			</div>
		</div>
		@endif
		
		<div class="pt-4">
			<div class="col-12 d-flex">
				<button type="submit" class="btn btn-primary me-sm-3 me-1">
					{{ isset($data->id) ? 'Update' : 'Create' }} Template
				</button>
				<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('template.index') }}'">Cancel</button>
			</div>
		</div>
	</form>
</div>
@endsection
