@extends('layouts/layoutMaster')

@section('title', ' Messages')

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
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">Messages/</span> {{ isset($data->id) ? 'Edit' : 'Create' }}</h4>
        <p class="text-muted">Manage your messages with ease and keep your audience engaged!</p>
    </div>
</div>

<div class="card mb-4">
	<h5 class="card-header">Messages</h5>
	<form class="card-body" action="{{ route('message.store') }}" method="POST">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">

		<div class="row g-3">
			<div class="col-md-6">
				<x-input-general id="name" label="Name (*)" value="{{ old('name', $data->name?? '') }}" />
			</div>
			<div class="col-md-4">
				<x-module-categories-select
					id="category_id"
					label="Categoría"
					moduleKey="contacts"
					:selected="old('category_id', $data->category_id ?? '')"
				/>
			</div>
			<div class="col-md-2">
				<x-input-select
					id="contact_status_id"
					label="{{ __('Contact Status') }}"
					:options="$data->contactStatuses ?? []"
					value="{{ old('contact_status_id', $data->contact_status_id ?? '') }}"
				/>
			</div>
			<div class="col-md-6">
				<x-input-select id="type_id" label="Type (*)" :options="$data->types" value="{{ old('type_id', $data->type_id ?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-select id="template_id" label="Template" :options="$data->templates ?? []" value="{{ old('template_id', $data->template_id ?? '') }}" />
				<div class="form-text mt-1">
					¿No encuentras el template que buscas? <a href="{{ route('template.create') }}">Agregar nuevo template</a>
				</div>
			</div>
			<div class="col-md-12">
				<x-input-textarea id="text" label="Text (*)" value="{{ old('text', $data->text?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-general
					id="min_hours_between_emails"
					label="Minimum Hours Between Emails"
					type="number"
					min="0"
					step="1"
					value="{{ old('min_hours_between_emails', $data->min_hours_between_emails ?? 48) }}"
				/>
				<div class="form-text mt-1">
					Time to wait before sending another email to the same contact (default: 48 hours)
				</div>
			</div>
			<div class="col-xl-12 p-4">
				<div class="text-light small fw-medium">Options</div>
				<div class="demo-inline-spacing">
					<div class="form-check form-switch">
						<input class="form-check-input" type="checkbox" id="status_id" name="status_id" value="1" {{ old('status_id', $data->status_id ?? 0) == 1 ? 'checked' : '' }}>
						<label class="form-check-label" for="status_id">Active</label>
					</div>
					<div class="form-check form-switch">
						<input class="form-check-input" type="checkbox" id="show_unsubscribe" name="show_unsubscribe" value="1" {{ old('show_unsubscribe', $data->show_unsubscribe ?? 1) == 1 ? 'checked' : '' }}>
						<label class="form-check-label" for="show_unsubscribe">Show Unsubscribe Link</label>
					</div>
				</div>
				<div class="text-light small fw-medium mt-3">Tracking Options</div>
				<div class="demo-inline-spacing">
					<div class="form-check form-switch">
						<input class="form-check-input" type="checkbox" id="enable_open_tracking" name="enable_open_tracking" value="1" {{ old('enable_open_tracking', $data->enable_open_tracking ?? 1) == 1 ? 'checked' : '' }}>
						<label class="form-check-label" for="enable_open_tracking">Enable Open Tracking</label>
					</div>
					<div class="form-check form-switch">
						<input class="form-check-input" type="checkbox" id="enable_click_tracking" name="enable_click_tracking" value="1" {{ old('enable_click_tracking', $data->enable_click_tracking ?? 1) == 1 ? 'checked' : '' }}>
						<label class="form-check-label" for="enable_click_tracking">Enable Click Tracking</label>
					</div>
				</div>
			</div>
		</div>
		<hr class="my-4 mx-n4" />

		<div class="pt-4">
			<button type="submit" class="btn btn-primary me-sm-3 me-1">Send</button>
			<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('message-list') }}'">Cancel</button>
		</div>
	</form>
</div>
@endsection
