@extends('layouts/layoutMaster')

@section('title', __('Projects'))

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
        // Inicializar Select2 si está disponible
        if ($.fn.select2) {
            $('#enterprise_id, #category_id, #status_id').select2({
                placeholder: "{{ __('Choose an option') }}",
                allowClear: true
            });
            // Note: #responsible_id is initialized by the team-users-select component
        }


    });
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Projects') }}/</span> {{ isset($data->id) ? __('Edit') : __('Create') }}</h4>
        <p class="text-muted">{{ __('Track your projects') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @can('project.index')
        <a href="{{ route('project-list') }}" class="btn btn-label-secondary waves-effect waves-light"><i class="ti ti-arrow-left me-1"></i>{{ __('Back to Projects') }}</a>
        @endcan
    </div>
</div>

<div class="card mb-4">
	<h5 class="card-header">{{ isset($data->id) ? __('Edit Project') : __('Add New Project') }}</h5>
	<form class="card-body" action="{{ route('project.store') }}" method="POST">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">
		
		@if($enterprise_id)
			<input type="hidden" name="enterprise_id" value="{{ $enterprise_id }}">
		@endif
		
		<div class="row g-4">
			<!-- Internal name for collaborators -->
			<div class="col-12">
				<label for="name" class="form-label">{{ __('Internal Name for Collaborators') }} <i class="ti ti-eye ms-1"></i></label>
				<input type="text" class="form-control" id="name" name="name" 
					   value="{{ old('name', $data->name ?? '') }}" 
					   placeholder="{{ __('What the collaborator sees') }}" required>
			</div>

			<!-- Real name -->
			<div class="col-12">
				<label for="real_name" class="form-label">{{ __('Real Name') }} <i class="ti ti-link ms-1"></i></label>
				<input type="text" class="form-control" id="real_name" name="real_name" 
					   value="{{ old('real_name', $data->real_name ?? '') }}" 
					   placeholder="{{ __('What the collaborator sees when accepting the project') }}">
			</div>

			<!-- Project status -->
			<div class="col-md-6">
				<label for="status_id" class="form-label">{{ __('Project Status') }}</label>
				<select class="form-select" id="status_id" name="status_id" required>
					@foreach($statuses as $status)
						<option value="{{ $status['id'] }}" 
							{{ (old('status_id', $data->status_id ?? '1') == $status['id']) ? 'selected' : '' }}>
							{{ $status['name'] }}
						</option>
					@endforeach
				</select>
			</div>

			<!-- Product type (Category) -->
			<div class="col-md-6">
				<x-module-categories-select 
					id="category_id" 
					label="{{ __('Product Type') }}" 
					moduleKey="projects"
					:selected="old('category_id', $data->category_id ?? '')" 
				/>
			</div>

			<!-- Dates -->
			<div class="col-md-6">
				<x-input-date id="date_material" label="{{ __('Material Delivery Date') }}" 
					value="{{ old('date_material', $data->date_material ?? '') }}" />
			</div>

			<div class="col-md-6">
				<x-input-date id="date_end" label="{{ __('Final Delivery Date') }}" 
					value="{{ old('date_end', $data->date_end ?? '') }}" />
			</div>

			<!-- Client -->
			@if(!$enterprise_id)
			<div class="col-12">
				<x-client-select 
					id="enterprise_id" 
					label="{{ __('Client') }} (*)" 
					:selected="old('enterprise_id', $data->enterprise_id ?? '')" 
				/>
			</div>
			@endif

			<!-- Additional fields for admins -->
			@if(auth()->user()->hasRole('admin'))
			{{-- Hidden: Price, discount and cost fields --}}
			{{-- 
			<div class="col-md-4">
				<label for="price" class="form-label">{{ __('Price') }}</label>
				<div class="input-group">
					<span class="input-group-text">€</span>
					<input type="number" class="form-control" id="price" name="price" 
						   step="0.01" min="0" value="{{ old('price', $data->price ?? '') }}">
				</div>
			</div>

			<div class="col-md-4">
				<label for="discount" class="form-label">{{ __('Discount') }} (%)</label>
				<input type="number" class="form-control" id="discount" name="discount" 
					   step="0.01" min="0" max="100" value="{{ old('discount', $data->discount ?? '') }}">
			</div>

			<div class="col-md-4">
				<label for="cost" class="form-label">{{ __('Cost') }}</label>
				<div class="input-group">
					<span class="input-group-text">€</span>
					<input type="number" class="form-control" id="cost" name="cost" 
						   step="0.01" min="0" value="{{ old('cost', $data->cost ?? '') }}">
				</div>
			</div>
			--}}

			{{-- Hidden: Start date field --}}
			{{-- 
			<div class="col-md-6">
				<x-input-date id="date_start" label="{{ __('Start Date') }}" 
					value="{{ old('date_start', $data->date_start ?? '') }}" />
			</div>
			--}}

			<div class="col-md-12">
				<x-team-users-select 
					id="responsible_id" 
					label="{{ __('Responsible') }} (*)" 
					role="admin"
					:selected="old('responsible_id', $data->responsible_id ?? auth()->id())" 
				/>
			</div>
			@else
			<!-- Simplified view for non-admins -->
			{{-- Hidden: Start date field --}}
			{{-- 
			<div class="col-md-6">
				<x-input-date id="date_start" label="{{ __('Start Date') }}" 
					value="{{ old('date_start', $data->date_start ?? '') }}" />
			</div>
			--}}

			<div class="col-md-12">
				<x-team-users-select 
					id="responsible_id" 
					label="{{ __('Responsible') }} (*)" 
					role="admin"
					:selected="old('responsible_id', $data->responsible_id ?? auth()->id())" 
				/>
			</div>
			@endif

			<!-- Notas del proyecto -->
			<div class="col-12">
				<label for="description" class="form-label">{{ __('Project Notes') }}</label>
				<textarea class="form-control" id="description" name="description" rows="6" 
						  placeholder="{{ __('Free text') }}" required>{{ old('description', $data->description ?? '') }}</textarea>
			</div>


		</div>
		
		<div class="pt-4">
			<div class="d-flex gap-3">
				<button type="submit" class="btn btn-primary px-5">{{ __('Save') }}</button>
				<button type="button" class="btn btn-label-secondary" onclick="location.href='{{ route('project-list') }}'">{{ __('Cancel') }}</button>
			</div>
		</div>
	</form>
</div>

@endsection