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
                    if (res.suggested_tasks && res.suggested_tasks.length) {
                        res.suggested_tasks.forEach(function(t) {
                            if (t.resource_level === undefined) t.resource_level = '';
                            if (t.unit_price === undefined) t.unit_price = '';
                        });
                        var html = buildSuggestedTasksTable(res.suggested_tasks);
                        $('#suggested-tasks-container').html(html).removeClass('d-none');
                        $('#suggested-tasks-toggle').removeClass('d-none');
                        $('#data_suggested_tasks').val(JSON.stringify(res.suggested_tasks));
                        refreshBudgetPreview();
                        $('#suggested-tasks-container').addClass('d-none');
                    } else {
                        $('#suggested-tasks-container').addClass('d-none').empty();
                        $('#suggested-tasks-toggle').addClass('d-none');
                        $('#data_suggested_tasks').val('');
                    }
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

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function buildSuggestedTasksTable(tasks) {
        var h = '<p class="text-muted small mb-2">' + (tasks.length === 1 ? '{{ __("1 task suggested") }}' : '{{ __(":count tasks suggested") }}'.replace(':count', tasks.length)) + '</p><div class="table-responsive"><table class="table table-sm table-bordered" id="suggested-tasks-table"><thead><tr><th class="text-center" style="width: 2.5rem;"></th><th>{{ __("Task") }}</th><th class="text-center">{{ __("Category") }}</th><th class="text-end">{{ __("Hours") }}</th><th class="text-center">{{ __("Level") }}</th><th class="text-end">{{ __("Value") }}</th></tr></thead><tbody>';
        tasks.forEach(function(t, i) {
            var included = t.included !== false;
            if (typeof t.included === 'undefined') t.included = true;
            var title = escapeHtml(t.title || '—');
            var cat = escapeHtml(t.category_name || '—');
            var hours = (t.estimated_hours != null ? Number(t.estimated_hours) : '—');
            var resLevel = (t.resource_level != null && t.resource_level !== '') ? escapeHtml(String(t.resource_level)) : '';
            var unitPrice = (t.unit_price != null && t.unit_price !== '') ? escapeHtml(String(t.unit_price)) : '';
            h += '<tr data-index="' + i + '"><td class="text-center align-middle"><input type="checkbox" class="form-check-input suggested-task-included" data-index="' + i + '" ' + (included ? 'checked' : '') + '></td><td>' + title + '</td><td class="text-center">' + cat + '</td><td class="text-end">' + hours + '</td>';
            h += '<td class="text-center"><input type="text" class="form-control form-control-sm suggested-resource-level" data-index="' + i + '" value="' + resLevel + '" placeholder="{{ __("e.g. Senior") }}"></td>';
            h += '<td class="text-end"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end suggested-unit-price" data-index="' + i + '" value="' + unitPrice + '" placeholder="0"></td></tr>';
        });
        h += '</tbody></table></div>';
        return h;
    }

    function refreshBudgetPreview() {
        var raw = $('#data_suggested_tasks').val();
        var tasks = [];
        try {
            if (raw) tasks = JSON.parse(raw);
        } catch (e) { }
        var summaryEl = $('#budget-preview-summary');
        if (tasks.length === 0) {
            $('#budget-preview-container').addClass('d-none');
            summaryEl.val('');
            return;
        }
        $('#budget-preview-container').removeClass('d-none');
        var lines = ['{{ __("Summary of requested quote and values") }}', ''];
        var total = 0;
        var totalHours = 0;
        function strikethroughUtf8(text) {
            return Array.from(String(text)).map(function(c) { return c + '\u0336'; }).join('');
        }
        tasks.forEach(function(t) {
            var title = (t.title || '—');
            var included = t.included !== false;
            if (included) {
                lines.push('• ' + title);
                var price = (t.unit_price != null && t.unit_price !== '') ? parseFloat(t.unit_price) : NaN;
                if (!isNaN(price)) total += price;
                var hours = (t.estimated_hours != null && t.estimated_hours !== '') ? parseFloat(t.estimated_hours) : 0;
                totalHours += hours;
            } else {
                lines.push('• ' + strikethroughUtf8(title));
            }
        });
        lines.push('');
        var totalRounded = Math.round(total);
        var totalFormatted = totalRounded.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        lines.push('{{ __("Total") }}: ' + totalFormatted + '€ + {{ __("I.V.A.") }}');
        var weeks = totalHours > 0 ? Math.ceil(totalHours / 40) : 0;
        lines.push('');
        lines.push('{{ __("Estimated development time, :weeks weeks after the budget has been confirmed.") }}'.replace(':weeks', weeks));
        summaryEl.val(lines.join('\n'));
    }

    $(document).on('change', '.suggested-task-included', function() {
        var idx = parseInt($(this).data('index'), 10);
        var raw = $('#data_suggested_tasks').val();
        var tasks = [];
        try {
            if (raw) tasks = JSON.parse(raw);
        } catch (e) { return; }
        if (tasks[idx] === undefined) return;
        tasks[idx].included = $(this).prop('checked');
        $('#data_suggested_tasks').val(JSON.stringify(tasks));
        refreshBudgetPreview();
    });

    $(document).on('change input', '.suggested-resource-level, .suggested-unit-price', function() {
        var idx = parseInt($(this).data('index'), 10);
        var raw = $('#data_suggested_tasks').val();
        var tasks = [];
        try {
            if (raw) tasks = JSON.parse(raw);
        } catch (e) { return; }
        if (tasks[idx] === undefined) return;
        var isPrice = $(this).hasClass('suggested-unit-price');
        var val = $(this).val();
        if (isPrice) {
            var num = parseFloat(val);
            tasks[idx].unit_price = (isNaN(num) || val === '') ? '' : num;
        } else {
            tasks[idx].resource_level = val;
        }
        $('#data_suggested_tasks').val(JSON.stringify(tasks));
        refreshBudgetPreview();
    });

    $('#suggested-tasks-toggle-btn').on('click', function() {
        var container = $('#suggested-tasks-container');
        var btn = $(this);
        var isHidden = container.hasClass('d-none');
        container.toggleClass('d-none');
        btn.attr('aria-expanded', isHidden);
        btn.find('.ti-chevron-down').toggleClass('ti-chevron-down', !isHidden).toggleClass('ti-chevron-up', isHidden);
        btn.find('.toggle-label').text(isHidden ? '{{ __("Hide breakdown") }}' : '{{ __("Edit breakdown") }}');
    });

    $(function() {
        refreshBudgetPreview();
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
			<!-- Vista previa: resumen para copiar en email (alineado con pedido de cotización) -->
			<div class="col-12 d-none mt-2" id="budget-preview-container">
				<label for="budget-preview-summary" class="form-label">{{ __('Budget preview') }}</label>
				<p class="text-muted small mb-1">{{ __('Summary of requested quote and values, ready to copy into an email.') }}</p>
				@if(isset($data->id) && data_get($data, 'data.budget_preview_token'))
					<p class="small mb-1">
						<a href="{{ route('project.budget-preview', data_get($data, 'data.budget_preview_token')) }}" target="_blank" rel="noopener noreferrer">{{ __('Preview') }}</a>
					</p>
				@endif
				<textarea id="budget-preview-summary" class="form-control font-monospace" rows="12" readonly placeholder="{{ __('Generate from budget text to see the summary here.') }}"></textarea>
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

			<!-- Suggested tasks (filled by AI, persisted in project data). Hidden by default; show via "Edit breakdown" link. -->
			<input type="hidden" name="data[suggested_tasks]" id="data_suggested_tasks" value="{{ json_encode(old('data.suggested_tasks', data_get($data, 'data.suggested_tasks', []))) }}">
			@php $savedSuggested = old('data.suggested_tasks', data_get($data, 'data.suggested_tasks', [])); @endphp
			<div class="col-12 mb-2 {{ empty($savedSuggested) || !is_array($savedSuggested) ? 'd-none' : '' }}" id="suggested-tasks-toggle">
				<button type="button" class="btn btn-sm btn-label-secondary" id="suggested-tasks-toggle-btn" aria-expanded="false">
					<i class="ti ti-chevron-down me-1"></i><span class="toggle-label">{{ __('Edit breakdown') }}</span>
				</button>
			</div>
			<div class="col-12 d-none" id="suggested-tasks-container">
				@if(!empty($savedSuggested) && is_array($savedSuggested))
					<p class="text-muted small mb-2">{{ count($savedSuggested) === 1 ? __('1 task suggested') : __(':count tasks suggested', ['count' => count($savedSuggested)]) }}</p>
					<div class="table-responsive">
						<table class="table table-sm table-bordered" id="suggested-tasks-table">
							<thead><tr><th class="text-center" style="width: 2.5rem;"></th><th>{{ __('Task') }}</th><th class="text-center">{{ __('Category') }}</th><th class="text-end">{{ __('Hours') }}</th><th class="text-center">{{ __('Level') }}</th><th class="text-end">{{ __('Value') }}</th></tr></thead>
							<tbody>
								@foreach($savedSuggested as $i => $t)
								@php $included = ($t['included'] ?? true); @endphp
								<tr data-index="{{ $i }}">
									<td class="text-center align-middle"><input type="checkbox" class="form-check-input suggested-task-included" data-index="{{ $i }}" {{ $included ? 'checked' : '' }}></td>
									<td>{{ $t['title'] ?? '—' }}</td>
									<td class="text-center">{{ $t['category_name'] ?? '—' }}</td>
									<td class="text-end">{{ isset($t['estimated_hours']) ? number_format((float) $t['estimated_hours'], 1) : '—' }}</td>
									<td class="text-center"><input type="text" class="form-control form-control-sm suggested-resource-level" data-index="{{ $i }}" value="{{ $t['resource_level'] ?? '' }}" placeholder="{{ __('e.g. Senior') }}"></td>
									<td class="text-end"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end suggested-unit-price" data-index="{{ $i }}" value="{{ isset($t['unit_price']) && $t['unit_price'] !== '' ? (float) $t['unit_price'] : '' }}" placeholder="0"></td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				@endif
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
