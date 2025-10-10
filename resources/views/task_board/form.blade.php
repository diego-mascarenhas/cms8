@extends('layouts/layoutMaster')

@section('title', __('Task Board'))

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
        // Any additional initialization
    });
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Task Boards') }}/</span> {{ isset($data->id) ? __('Edit') : __('Create') }}</h4>
        <p class="text-muted">{{ __('Manage your Kanban boards') }}</p>
    </div>
</div>

<div class="card mb-4">
	<h5 class="card-header">{{ __('Task Board') }}</h5>
	<form class="card-body" action="{{ route('task-board.store') }}" method="POST">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">
		
		<div class="row g-3">
			<div class="col-md-12">
				<div class="mb-3">
					<label for="name" class="form-label">{{ __('Name') }} (*)</label>
					<input type="text" class="form-control" id="name" name="name" 
						value="{{ old('name', $data->name ?? '') }}" required>
					@error('name')
						<div class="text-danger">{{ $message }}</div>
					@enderror
				</div>
			</div>
			
			<div class="col-md-12">
				<div class="mb-3">
					<label for="description" class="form-label">{{ __('Description') }}</label>
					<textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $data->description ?? '') }}</textarea>
				</div>
			</div>
			
			<div class="col-md-12">
				<div class="form-check mb-3">
					<input class="form-check-input" type="checkbox" id="is_default" name="is_default" 
						value="1" {{ old('is_default', $data->is_default ?? false) ? 'checked' : '' }}>
					<label class="form-check-label" for="is_default">{{ __('Set as default board') }}</label>
				</div>
			</div>
		</div>
		
		<div class="pt-4">
			<div class="col-12 d-flex">
				<button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Save') }}</button>
				<button type="button" class="btn btn-label-secondary" onclick="location.href='{{ route('task-board.index') }}'">{{ __('Cancel') }}</button>
			</div>
		</div>
	</form>
</div>
@endsection