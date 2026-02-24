@extends('layouts/layoutMaster')

@section('title', __('Projects'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
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

    @if(isset($data->id))
    // Function to delete project
    function deleteProject(projectId, projectName) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: `¿Deseas eliminar el proyecto "${projectName}"? Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            customClass: {
                confirmButton: 'btn btn-danger me-3',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then(function (result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/project/${projectId}`,
                    type: 'DELETE',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                    },
                    success: function (response) {
                        Swal.fire({
                            title: 'Eliminado!',
                            text: 'El proyecto ha sido eliminado exitosamente.',
                            icon: 'success',
                            customClass: {
                                confirmButton: 'btn btn-success'
                            },
                            buttonsStyling: false
                        }).then(function() {
                            // Redirect to projects list
                            window.location.href = '{{ route("project-list") }}';
                        });
                    },
                    error: function (response) {
                        Swal.fire({
                            title: 'Error',
                            text: response.responseJSON?.message || 'Ha ocurrido un error al eliminar el proyecto',
                            icon: 'error',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            },
                            buttonsStyling: false
                        });
                    }
                });
            }
        });
    }
    @endif

    // Generate budget data from "Project notes" + "Budget received" (AI)
    $('#generate-budget-spec').on('click', function() {
        var notes = $('#description').val().trim();
        var budgetReceived = $('#data_budget_given').val().trim();
        var parts = [];
        if (notes) {
            parts.push('{{ __("Project Notes") }}:\n' + notes);
        }
        if (budgetReceived) {
            parts.push('{{ __("Budget received") }}:\n' + budgetReceived);
        }
        var budgetGiven = parts.join('\n\n');
        if (!budgetGiven) {
            Swal.fire({
                title: '{{ __("Description required") }}',
                text: '{{ __("Write or paste the budget text first, then click Generate.") }}',
                icon: 'info',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false
            });
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>{{ __("Generating...") }}');
        $.ajax({
            url: '{{ route("project.generate-budget-spec") }}',
            type: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                budget_given: budgetGiven
            },
            success: function(res) {
                if (res.success) {
                    $('#data_ai_interpretation').val(res.ai_interpretation || '');
                    $('#data_dimension').val(res.dimension || '');
                    $('#data_estimated_times').val(res.estimated_times || '');
                    $('#data_resources').val(res.resources || '');
                } else {
                    Swal.fire({
                        title: '{{ __("Error") }}',
                        text: res.message || '{{ __("Could not generate budget spec.") }}',
                        icon: 'error',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    });
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '{{ __("Request failed. Try again.") }}';
                Swal.fire({
                    title: '{{ __("Error") }}',
                    text: msg,
                    icon: 'error',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false
                });
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="ti ti-sparkles me-1"></i>{{ __("Generate from budget text") }}');
            }
        });
    });
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Projects') }}/</span> {{ isset($data->id) ? __('Edit') : __('Create') }}</h4>
        <p class="text-muted">{{ __('Track your projects') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3"></div>
</div>

<div class="card mb-4">
	<h5 class="card-header">{{ isset($data->id) ? __('Edit Project') : __('Add New Project') }}</h5>
	<form class="card-body" action="{{ route('project.store') }}" method="POST">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">

		@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

		<div class="row g-4">
			<!-- Internal name for collaborators -->
			<div class="col-12">
				<label for="name" class="form-label">{{ __('Internal Name for Collaborators') }} <i class="ti ti-eye ms-1"></i></label>
				<input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $data->name ?? '') }}">
				@error('name')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
			</div>

			<!-- Real name -->
			<div class="col-12">
				<label for="real_name" class="form-label">{{ __('Real Name') }} <i class="ti ti-link ms-1"></i></label>
				<input type="text" name="real_name" class="form-control @error('real_name') is-invalid @enderror" value="{{ old('real_name', $data->real_name ?? '') }}">
				@error('real_name')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
			</div>

			<!-- Project status -->
			<div class="col-md-6">
				<label for="status_id" class="form-label">{{ __('Project Status') }}</label>
				<select name="status_id" class="form-control @error('status_id') is-invalid @enderror">
					@foreach($statuses as $status)
						<option value="{{ $status['id'] }}" {{ old('status_id', $data->status_id ?? '') == $status['id'] ? 'selected' : '' }}>{{ $status['name'] }}</option>
					@endforeach
				</select>
				@error('status_id')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
			</div>

			<!-- Category -->
			<div class="col-md-6">
				<x-module-categories-select
					id="category_id"
					label="{{ __('Categoría') }}"
					moduleKey="projects"
					:selected="is_array(old('category_id', $data->category_id ?? '')) ? (old('category_id', $data->category_id ?? '')[0] ?? '') : old('category_id', $data->category_id ?? '')"
				/>
				@error('category_id')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
			</div>

			<!-- Dates -->
			<div class="col-md-6">
				<x-input-date id="date_material" name="date_material" label="{{ __('Material Delivery Date') }}"
					value="{{ old('date_material', $data->date_material ?? '') }}" />
			</div>

			<div class="col-md-6">
				<x-input-date id="date_end" label="{{ __('Final Delivery Date') }}"
					value="{{ old('date_end', $data->date_end ?? '') }}" />
				@error('date_end')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
			</div>

			<!-- Client -->
			<div class="col-12">
				<x-client-select
					id="enterprise_id"
					label="{{ __('Client') }} (*)"
					:selected="old('enterprise_id', $data->enterprise_id ?? '')"
				/>
				@error('enterprise_id')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
			</div>

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
					label="{{ __('Asesor') }} (*)"
					:selected="old('responsible_id', $data->responsible_id ?? auth()->id())"
				/>
				@error('responsible_id')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
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
					label="{{ __('Asesor') }} (*)"
					:selected="old('responsible_id', $data->responsible_id ?? auth()->id())"
				/>
				@error('responsible_id')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
			</div>
			@endif

			<!-- Notas del proyecto -->
			<div class="col-12">
				<label for="description" class="form-label">{{ __('Project Notes') }}</label>
				<textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="4">{{ old('description', $data->description ?? '') }}</textarea>
				@error('description')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
			</div>

			<!-- Presupuesto: texto recibido + datos interpretados (data JSON) -->
			<div class="col-12">
				<label for="data_budget_given" class="form-label">{{ __('Budget received') }}</label>
				<textarea id="data_budget_given" name="data[budget_given]" class="form-control" rows="3" placeholder="{{ __('Paste or type the budget text you received from the client') }}">{{ old('data.budget_given', data_get($data, 'data.budget_given', '')) }}</textarea>
			</div>
			<div class="col-12">
				<div class="d-flex justify-content-between align-items-center mb-2">
					<label class="form-label mb-0">{{ __('Budget data (AI)') }}</label>
					<button type="button" id="generate-budget-spec" class="btn btn-outline-primary btn-sm">
						<i class="ti ti-sparkles me-1"></i>{{ __('Generate from budget text') }}
					</button>
				</div>
				<p class="text-muted small">{{ __('Use "Budget received" above, then click to generate AI interpretation, dimension, timeline and resources.') }}</p>
			</div>
			<div class="col-12">
				<label for="data_ai_interpretation" class="form-label">{{ __('AI interpretation') }}</label>
				<textarea id="data_ai_interpretation" name="data[ai_interpretation]" class="form-control" rows="2">{{ old('data.ai_interpretation', data_get($data, 'data.ai_interpretation', '')) }}</textarea>
			</div>
			<div class="col-md-4">
				<label for="data_dimension" class="form-label">{{ __('Dimension') }}</label>
				<textarea id="data_dimension" name="data[dimension]" class="form-control" rows="3">{{ old('data.dimension', data_get($data, 'data.dimension', '')) }}</textarea>
			</div>
			<div class="col-md-4">
				<label for="data_estimated_times" class="form-label">{{ __('Estimated times') }}</label>
				<textarea id="data_estimated_times" name="data[estimated_times]" class="form-control" rows="3">{{ old('data.estimated_times', data_get($data, 'data.estimated_times', '')) }}</textarea>
			</div>
			<div class="col-md-4">
				<label for="data_resources" class="form-label">{{ __('Resources') }}</label>
				<textarea id="data_resources" name="data[resources]" class="form-control" rows="3">{{ old('data.resources', data_get($data, 'data.resources', '')) }}</textarea>
			</div>

		</div>

		<div class="pt-4">
			<div class="d-flex gap-3">
				<button type="submit" class="btn btn-primary px-5">{{ __('Save') }}</button>
				@if(isset($data->id))
					<button type="button" class="btn btn-label-secondary" onclick="location.href='{{ route('project.show', $data->id) }}'">{{ __('Cancel') }}</button>
				@else
					<button type="button" class="btn btn-label-secondary" onclick="location.href='{{ route('project-list') }}'">{{ __('Cancel') }}</button>
				@endif
			</div>
		</div>
	</form>
</div>

@endsection
