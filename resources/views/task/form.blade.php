@extends('layouts/layoutMaster')

@section('title', __('Tasks'))

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

<script>
    $(function() {
        // Inicializar Select2 si está disponible
        if ($.fn.select2) {
            $('#unit_ids, #type_id, #board_id').select2();
        }
    });
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Tasks') }}/</span> {{ isset($data->id) ? __('Edit') : __('Create') }}</h4>
        <p class="text-muted">{{ __('Track your tasks') }}</p>
    </div>
</div>

<div class="card mb-4">
	<h5 class="card-header">Tasks</h5>
	<form class="card-body" action="{{ route('task.store') }}" method="POST">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">
		@if(request()->has('view') && request()->view === 'kanban')
			<input type="hidden" name="view" value="kanban">
		@endif
		
		<div class="row g-3">
			<div class="col-md-6">
				<x-input-general id="title" label="Título (*)" value="{{ old('title', $data->title?? '') }}" />
			</div>
			<div class="col-md-3">
				<x-module-categories-select 
					id="category_id" 
					label="Categoría" 
					moduleKey="tasks"
					:selected="old('category_id', $data->category_id ?? '')" 
				/>
			</div>
			<div class="col-md-3">
				<x-input-select id="status_id" label="Estado (*)" :options="$statuses" value="{{ old('status_id', $data->status_id ?? request()->input('status_id', '1')) }}" />
			</div>
			
			<div class="col-md-3">
				<div class="mb-3">
					<label for="board_id" class="form-label">{{ __('Board') }}</label>
					<select id="board_id" name="board_id" class="form-select">
						@foreach($boards as $board)
							<option value="{{ $board['id'] }}" {{ (old('board_id', $defaultBoardId) == $board['id'] ? 'selected' : '') }}>
								{{ $board['name'] }}
							</option>
						@endforeach
					</select>
				</div>
			</div>

			@if(auth()->user()->hasRole('admin'))
			<div class="col-md-3">
				<x-input-date id="start_date" label="Fecha inicio (*)" 
					value="{{ old('start_date', $data->start_date ?? '') }}" />
			</div>
			
			<div class="col-md-3">
				<x-input-date id="due_date" label="Fecha finalización (*)" 
					value="{{ old('due_date', $data->due_date ?? '') }}" />
			</div>
			
			<div class="col-md-6">
				<x-team-users-select 
					id="responsible_id" 
					label="Responsible (*)" 
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
				<button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Save') }}</button>
				@if(request()->has('view') && request()->view === 'kanban')
					<button type="button" class="btn btn-label-secondary" onclick="location.href='{{ route('task.index', ['view' => 'kanban', 'board_id' => $defaultBoardId]) }}'">{{ __('Cancel') }}</button>
				@else
					<button type="button" class="btn btn-label-secondary" onclick="location.href='{{ route('task.index') }}'">{{ __('Cancel') }}</button>
				@endif
			</div>
		</div>
	</form>
</div>
@endsection