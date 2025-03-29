@extends('layouts/layoutMaster')

@section('title', ' Projects')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />

<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>

<script src="{{asset('assets/vendor/libs/quill/katex.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/form-layouts.js')}}"></script>

<script src="{{asset('assets/js/forms-editors.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">Projects/</span> {{ isset($data->id) ? 'Edit' : 'Create' }}</h4>
        <p class="text-muted">Track your projects</p>
    </div>
</div>

<div class="card mb-4">
	<h5 class="card-header">Project</h5>
	<form class="card-body" action="{{ route('project.store') }}" method="POST">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">

		@if(isset($enterprise_id) && $enterprise_id)
			<input type="hidden" name="enterprise_id" value="{{ $enterprise_id }}">
		@else
			<div class="col-md-12 mb-4">
				<x-client-select 
					id="enterprise_id" 
					label="Cliente (*)" 
					:selected="old('enterprise_id', $data->enterprise_id ?? '')"
					:allow-null="false"
				/>
			</div>
		@endif
		
		<div class="row g-3">
			<div class="col-md-6">
				<x-input-general id="name" label="Name (*)" value="{{ old('name', $data->name?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-input-select id="category_id" label="Categoría" :options="$categories" value="{{ old('category_id', $data->category_id ?? '') }}" />
			</div>
			
			@if(auth()->user()->hasRole('admin'))
			<div class="col-md-3">
				<x-input-general id="price" label="Precio" type="number" step="0.01" value="{{ old('price', $data->price ?? '') }}" />
			</div>
			<div class="col-md-3">
				<x-input-general id="discount" label="Descuento (%)" type="number" step="1" min="0" max="20" pattern="\d*" value="{{ old('discount', intval($data->discount ?? 0)) }}" />
			</div>
			<div class="col-md-3">
				<x-input-general id="cost" label="Costo" type="number" step="0.01" value="{{ old('cost', $data->cost ?? '') }}" />
			</div>
			<div class="col-md-3">
				<x-input-select id="status_id" label="Estado" :options="$statuses" value="{{ old('status_id', $data->status_id ?? '1') }}" />
			</div>
			@else
			<div class="col-md-4">
				<x-input-select id="status_id" label="Estado" :options="$statuses" value="{{ old('status_id', $data->status_id ?? '1') }}" />
			</div>
			<div class="col-md-4">
				<x-input-date id="start_date" label="Fecha inicio" 
					value="{{ old('start_date', $data->start_date ?? '') }}" />
			</div>
			<div class="col-md-4">
				<x-input-date id="end_date" label="Fecha finalización" 
					value="{{ old('end_date', $data->end_date ?? '') }}" />
			</div>
			<div class="col-md-12">
				<x-team-users-select 
					id="responsible_id" 
					label="Responsible" 
					:selected="old('responsible_id', $data->responsible_id ?? auth()->id())" 
				/>
			</div>
			@endif
			
			@if(auth()->user()->hasRole('admin'))
			<div class="col-md-3">
				<x-input-date id="start_date" label="Fecha inicio" 
					value="{{ old('start_date', $data->start_date ?? '') }}" />
			</div>
			
			<div class="col-md-3">
				<x-input-date id="end_date" label="Fecha finalización" 
					value="{{ old('end_date', $data->end_date ?? '') }}" />
			</div>
			
			<div class="col-md-6">
				<x-team-users-select 
					id="responsible_id" 
					label="Responsible" 
					:selected="old('responsible_id', $data->responsible_id ?? auth()->id())" 
				/>
			</div>
			@endif
			
			<div class="col-md-12">
				<x-input-textarea id="description" label="Description (*)" value="{{ old('description', $data->description?? '') }}" />
			</div>
		</div>
		
		<div class="pt-4">
			<div class="col-12 d-flex">
				<button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
				<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('project-list') }}'">Cancelar</button>
			</div>
		</div>
	</form>
</div>

<script>
    // Initialize Quill editor
    var quill = new Quill('#snow-editor', {
        theme: 'snow',
        modules: {
            toolbar: '#snow-toolbar'
        }
    });

    // Form submission handler
    document.querySelector('form').onsubmit = function() {
        // Update hidden input field with editor content
        document.querySelector('#description').value = quill.root.innerHTML;
    };
</script>

@endsection