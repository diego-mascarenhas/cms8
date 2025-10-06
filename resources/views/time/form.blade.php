@extends('layouts/layoutMaster')

@section('title', __('Times'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/form-layouts.js')}}"></script>

<script>
	$(function() {
		// Initialize Select2
		if ($.fn.select2) {
			$('#project_id, #task_id').select2();
		}

		// Initialize Flatpickr for date-time
		if ($.fn.flatpickr) {
			$('#start_time, #end_time').flatpickr({
				enableTime: true,
				dateFormat: "Y-m-d H:i",
				time_24hr: true
			});
		}
	});
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Times') }}/</span> {{ isset($data->id) ? __('Edit') : __('Create') }}</h4>
		<p class="text-muted">{{ __('Add time entry manually') }}</p>
	</div>
</div>

<div class="card mb-4">
	<h5 class="card-header">{{ isset($data->id) ? __('Edit Time Entry') : __('New Time Entry') }}</h5>
	<form class="card-body" action="{{ isset($data->id) ? route('time.update', $data->id) : route('time.store') }}" method="POST">
		@csrf
		@if(isset($data->id))
			@method('PUT')
		@endif

		<div class="row g-3">
			<div class="col-md-6">
				<label for="project_id" class="form-label">{{ __('Project') }}</label>
				<select id="project_id" name="project_id" class="form-select">
					<option value="">{{ __('Select Project (Optional)') }}</option>
					@foreach($projects as $project)
						<option value="{{ $project->id }}" {{ old('project_id', $data->project_id ?? '') == $project->id ? 'selected' : '' }}>
							{{ $project->name }}
						</option>
					@endforeach
				</select>
				@error('project_id')
					<div class="invalid-feedback d-block">{{ $message }}</div>
				@enderror
			</div>

			<div class="col-md-6">
				<label for="task_id" class="form-label">{{ __('Task') }}</label>
				<select id="task_id" name="task_id" class="form-select">
					<option value="">{{ __('Select Task (Optional)') }}</option>
					@foreach($tasks as $task)
						<option value="{{ $task->id }}" {{ old('task_id', $data->task_id ?? '') == $task->id ? 'selected' : '' }}>
							{{ $task->title }}
						</option>
					@endforeach
				</select>
				@error('task_id')
					<div class="invalid-feedback d-block">{{ $message }}</div>
				@enderror
			</div>

			<div class="col-md-6">
				<label for="start_time" class="form-label">{{ __('Start Time') }} (*)</label>
				<input type="text"
					class="form-control @error('start_time') is-invalid @enderror"
					id="start_time"
					name="start_time"
					value="{{ old('start_time', isset($data) ? $data->start_time->format('Y-m-d H:i') : now()->format('Y-m-d H:i')) }}"
					required>
				@error('start_time')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>

			<div class="col-md-6">
				<label for="end_time" class="form-label">{{ __('End Time') }}</label>
				<input type="text"
					class="form-control @error('end_time') is-invalid @enderror"
					id="end_time"
					name="end_time"
					value="{{ old('end_time', isset($data) && $data->end_time ? $data->end_time->format('Y-m-d H:i') : '') }}">
				@error('end_time')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
				<small class="text-muted">{{ __('Leave empty if still in progress') }}</small>
			</div>

			<div class="col-md-12">
				<label for="description" class="form-label">{{ __('Description') }}</label>
				<textarea
					class="form-control @error('description') is-invalid @enderror"
					id="description"
					name="description"
					rows="3">{{ old('description', $data->description ?? '') }}</textarea>
				@error('description')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>

			<div class="col-md-6">
				<label for="hourly_rate" class="form-label">{{ __('Hourly Rate') }}</label>
				<div class="input-group">
					<span class="input-group-text">$</span>
					<input type="number"
						class="form-control @error('hourly_rate') is-invalid @enderror"
						id="hourly_rate"
						name="hourly_rate"
						step="0.01"
						min="0"
						value="{{ old('hourly_rate', $data->hourly_rate ?? '') }}">
				</div>
				@error('hourly_rate')
					<div class="invalid-feedback d-block">{{ $message }}</div>
				@enderror
			</div>

			<div class="col-md-6">
				<label for="is_billable" class="form-label">{{ __('Billable') }}</label>
				<div class="form-check form-switch mt-2">
					<input
						class="form-check-input"
						type="checkbox"
						id="is_billable"
						name="is_billable"
						value="1"
						{{ old('is_billable', $data->is_billable ?? true) ? 'checked' : '' }}>
					<label class="form-check-label" for="is_billable">
						{{ __('This time is billable') }}
					</label>
				</div>
			</div>
		</div>

		<div class="pt-4">
			<div class="col-12 d-flex">
				<button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Save') }}</button>
				<button type="button" class="btn btn-label-secondary" onclick="location.href='{{ route('time.index') }}'">{{ __('Cancel') }}</button>
			</div>
		</div>
	</form>
</div>
@endsection
