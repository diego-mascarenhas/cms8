@extends('layouts/layoutMaster')

@section('title', $project->name)

@section('vendor-style')
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
@endsection

@section('page-style')
	<style>
		.timeline-center::before {
			left: 50%;
			transform: translateX(-50%);
		}
		.timeline-center .timeline-item:nth-child(odd) .timeline-event {
			text-align: right;
		}
		.timeline-center .timeline-item:nth-child(odd) .timeline-point {
			left: 50%;
			transform: translateX(-50%);
		}
		.timeline-center .timeline-item:nth-child(even) .timeline-point {
			left: 50%;
			transform: translateX(-50%);
		}
		.project-stats .card {
			border: none;
			box-shadow: 0 2px 6px 0 rgba(67, 89, 113, 0.12);
			transition: transform 0.2s;
		}
		.project-stats .card:hover {
			transform: translateY(-2px);
		}
		.timeline-card {
			max-height: 600px;
			overflow-y: auto;
		}
	</style>
@endsection

@section('vendor-script')
	<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
	<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
	<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('content')
	<!-- Header -->
	<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
		<div class="d-flex flex-column justify-content-center">
			<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Projects') }}/</span> {{ $project->real_name ?? $project->name }}</h4>
			<p class="text-muted">{{ __('Created on') }} {{ \Carbon\Carbon::parse($project->created_at)->format('F d, Y') }}</p>
		</div>
		<div class="d-flex align-content-center flex-wrap gap-3">
			@can('update', $project)
				<a href="{{ route('project.edit', $project->id) }}" class="btn btn-primary waves-effect waves-light">
					<i class="ti ti-edit me-1"></i>{{ __('Edit') }}
				</a>
			@endcan
			@can('update', $project)
			<a href="{{ route('project.select-collaborators', $project->id) }}" class="btn btn-success waves-effect waves-light">
				<i class="ti ti-users me-1"></i>{{ __('Collaborators') }}
			</a>
			@endcan
			<a href="{{ route('task.index', ['view' => 'kanban', 'project_id' => $project->id]) }}" class="btn btn-info waves-effect waves-light">
				<i class="ti ti-layout-kanban me-1"></i>{{ __('Board') }}
			</a>
			@if(data_get($project->data, 'budget_preview_token'))
				<a href="{{ route('project.budget-preview', data_get($project->data, 'budget_preview_token')) }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary waves-effect waves-light">
					<i class="ti ti-file-invoice me-1"></i>{{ __('Preview') }}
				</a>
			@endif
			@role('admin|collaborator|developer|editor|technical')
				<a href="{{ route('project-list') }}" class="btn btn-label-secondary waves-effect waves-light"><i class="ti ti-arrow-left me-1"></i>{{ __('Back') }}</a>
			@endrole
		</div>
	</div>

<!-- Project Details Card - Full Width -->
<div class="card mb-4">
   <div class="card-header">
       <h5 class="mb-0">{{ __('Project Details') }}</h5>
   </div>
   <div class="card-body">
       <div class="row">
           <div class="col-md-6">
               <dl class="row mb-0">
								<dt class="col-4 text-truncate">{{ __('Client') }}:</dt>
								<dd class="col-8">{{ $project->client ? $project->client->name : __('Not assigned') }}</dd>

								<dt class="col-4 text-truncate">{{ __('Category') }}:</dt>
								<dd class="col-8">{{ $project->category ? $project->category->name : __('Not assigned') }}</dd>

								<dt class="col-4 text-truncate">{{ __('Responsible') }}:</dt>
								<dd class="col-8">{{ $project->responsible ? $project->responsible->name : __('Not assigned') }}</dd>

								@if($project->client && $project->client->responsible_id)
								<dt class="col-4 text-truncate">{{ __('Contact') }}:</dt>
								<dd class="col-8">
									<a href="{{ route('contact.show', $project->client->responsible_id) }}">
										{{ $project->client->responsible ? $project->client->responsible->name : 'Contact #' . $project->client->responsible_id }}
									</a>
								</dd>
								@endif
							</dl>
           </div>
           <div class="col-md-6">
               <dl class="row mb-0">
								<dt class="col-4 text-truncate">{{ __('Start Date') }}:</dt>
								<dd class="col-8">{{ $project->date_start ? \Carbon\Carbon::parse($project->date_start)->format('d/m/Y') : __('Not set') }}</dd>

								@if($project->date_material)
								<dt class="col-4 text-truncate">{{ __('Material Date') }}:</dt>
								<dd class="col-8">{{ \Carbon\Carbon::parse($project->date_material)->format('d/m/Y') }}</dd>
								@endif

								<dt class="col-4 text-truncate">{{ __('End Date') }}:</dt>
								<dd class="col-8">{{ $project->date_end ? \Carbon\Carbon::parse($project->date_end)->format('d/m/Y') : __('Not set') }}</dd>

								@if(auth()->user()->hasRole('admin') && $project->discount)
								<dt class="col-4 text-truncate">{{ __('Discount') }}:</dt>
								<dd class="col-8">{{ $project->discount }}%</dd>
								@endif
							</dl>
           </div>
       </div>
   </div>
</div>

<!-- Tasks and times block -->
<div class="card mb-4">
	<div class="card-header d-flex justify-content-between align-items-center">
		<h5 class="mb-0">{{ __('Tasks and times') }}</h5>
		<a href="{{ route('task.index', ['view' => 'kanban', 'project_id' => $project->id]) }}" class="btn btn-sm btn-outline-primary">
			<i class="ti ti-layout-kanban me-1"></i>{{ __('Open Kanban board') }}
		</a>
	</div>
	<div class="card-body">
		@if(isset($projectTasks) && $projectTasks->isNotEmpty())
			@php
				$totalEstimated = $projectTasks->sum('estimated_hours');
				$totalActual = $actualHoursByTaskId->sum();
			@endphp
			<p class="text-muted small mb-3">{{ __('Estimated and actual hours per task.') }}</p>
			<div class="d-flex gap-2 mb-3">
				<span class="badge bg-label-primary">{{ __('Estimated') }}: {{ number_format($totalEstimated, 1) }}h</span>
				<span class="badge bg-label-info">{{ __('Actual') }}: {{ number_format($totalActual, 1) }}h</span>
			</div>
			<div class="table-responsive">
				<table class="table table-bordered table-hover">
					<thead>
						<tr>
							<th>{{ __('Task') }}</th>
							<th class="text-center">{{ __('Responsible') }}</th>
							<th class="text-center">{{ __('Status') }}</th>
							<th class="text-end">{{ __('Hours') }}</th>
							<th class="text-end">{{ __('Actual (h)') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach($projectTasks as $task)
						<tr>
							<td>
								<a href="{{ route('task.show', $task->id) }}">{{ $task->title ?? '—' }}</a>
							</td>
							<td class="text-center">{{ $task->responsible?->name ?? '—' }}</td>
							<td class="text-center">
								@if($task->status)
									<span class="badge rounded-pill {{ $task->status->label_class ?? 'bg-label-secondary' }}">{{ $task->status->translated_name ?? $task->status->name }}</span>
								@else
									—
								@endif
							</td>
							<td class="text-end">{{ $task->estimated_hours !== null && $task->estimated_hours !== '' ? number_format((float) $task->estimated_hours, 1) : '—' }}</td>
							<td class="text-end">{{ number_format($actualHoursByTaskId->get($task->id, 0), 1) }}</td>
						</tr>
						@endforeach
					</tbody>
					<tfoot>
						<tr class="fw-semibold">
							<td colspan="3" class="text-end">{{ __('Total') }}</td>
							<td class="text-end">{{ number_format($totalEstimated, 1) }}h</td>
							<td class="text-end">{{ number_format($totalActual, 1) }}h</td>
						</tr>
					</tfoot>
				</table>
			</div>
		@else
			<p class="text-muted mb-0">{{ __('No tasks on this project board yet. Add tasks in the Kanban to see estimated and actual hours here.') }}</p>
		@endif

		@if(isset($suggestedTasks) && count($suggestedTasks) > 0)
			@php
				$suggestedTotalHours = collect($suggestedTasks)->sum(fn ($t) => ($t['included'] ?? true) && isset($t['estimated_hours']) && $t['estimated_hours'] !== '' && $t['estimated_hours'] !== null ? (float) $t['estimated_hours'] : 0);
			@endphp
			<hr class="my-4">
			<p class="text-muted small mb-3">{{ __('Suggested tasks from the budget. Assign who will do each and add them to the board.') }}</p>
			<div class="table-responsive">
				<table class="table table-bordered table-hover">
					<thead>
						<tr>
							<th>{{ __('Task') }}</th>
							<th class="text-center">{{ __('Task category') }}</th>
							<th class="text-end">{{ __('Hours') }}</th>
							<th class="text-center">{{ __('Level') }}</th>
							<th style="min-width: 220px;">{{ __('Who will do it') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach($suggestedTasks as $idx => $t)
						@php $suggestedIncluded = $t['included'] ?? true; @endphp
						<tr class="{{ $suggestedIncluded ? '' : 'table-secondary' }}">
							<td>{{ $t['title'] ?? '—' }}</td>
							<td class="text-center">{{ $t['category_name'] ?? '—' }}</td>
							<td class="text-end">{{ isset($t['estimated_hours']) ? number_format((float) $t['estimated_hours'], 1) : '—' }}</td>
							<td class="text-center">{{ $t['resource_level'] ?? '—' }}</td>
							<td>
								<form action="{{ route('project.add-suggested-task', $project->id) }}" method="POST" class="d-flex align-items-center gap-2">
									@csrf
									<input type="hidden" name="title" value="{{ $t['title'] ?? '' }}">
									<input type="hidden" name="category_name" value="{{ $t['category_name'] ?? '' }}">
									<input type="hidden" name="estimated_hours" value="{{ $t['estimated_hours'] ?? '' }}">
									<select name="responsible_id" class="form-select form-select-sm" {{ $suggestedIncluded ? '' : 'disabled' }} required>
										<option value="">{{ __('Select') }}</option>
										@foreach($teamUsers ?? [] as $userId => $userName)
											<option value="{{ $userId }}">{{ $userName }}</option>
										@endforeach
									</select>
									<button type="submit" class="btn btn-sm btn-primary" {{ $suggestedIncluded ? '' : 'disabled' }}>
										<i class="ti ti-layout-kanban me-1"></i>{{ __('Add') }}
									</button>
								</form>
							</td>
						</tr>
						@endforeach
					</tbody>
					<tfoot>
						<tr class="fw-semibold">
							<td colspan="2" class="text-end">{{ __('Total') }}</td>
							<td class="text-end">{{ number_format($suggestedTotalHours, 1) }}h</td>
							<td colspan="2"></td>
						</tr>
					</tfoot>
				</table>
			</div>
		@endif
	</div>
</div>

<!-- Time Tracking for Project Tasks -->
@if(isset($timeEntries))
<div class="card mb-4">
   <div class="card-header d-flex justify-content-between align-items-center">
       <h5 class="mb-0">{{ __('Recent time entries') }}</h5>
       <span class="badge bg-label-info">{{ __('Total') }}: {{ number_format($totalHours, 1) }}h</span>
   </div>
   <div class="card-body">
       @if($timeEntries->isEmpty())
           <p class="text-muted mb-0">{{ __('No time entries for this project') }}</p>
       @else
       <div class="table-responsive">
           <table class="table table-hover">
               <thead>
                   <tr>
                       <th>{{ __('User') }}</th>
                       <th class="text-center">{{ __('Start') }}</th>
                       <th class="text-center">{{ __('End') }}</th>
                       <th class="text-end">{{ __('Duration') }}</th>
                   </tr>
               </thead>
               <tbody>
                   @foreach($timeEntries as $entry)
                   <tr>
                       <td>{{ $entry->user?->name ?? '—' }}</td>
                       <td class="text-center">{{ optional($entry->start_time)->format('d/m/Y H:i') }}</td>
                       <td class="text-center">{{ optional($entry->end_time)->format('d/m/Y H:i') ?? '—' }}</td>
                       <td class="text-end">
                           @php
                               $seconds = $entry->duration_seconds ?? ($entry->end_time && $entry->start_time ? $entry->end_time->diffInSeconds($entry->start_time) : 0);
                               $hours = floor($seconds / 3600);
                               $minutes = floor(($seconds % 3600) / 60);
                           @endphp
                           {{ sprintf('%02dh %02dm', $hours, $minutes) }}
                       </td>
                   </tr>
                   @endforeach
               </tbody>
           </table>
       </div>
       @endif
   </div>
</div>
@endif

<!-- Collaborators Section (Floating Cards) -->
@if($project->allCollaborators && $project->allCollaborators->count() > 0)
<div class="row mb-4" style="align-items: stretch;">
    @foreach($project->allCollaborators as $index => $collaborator)
        @php
            // Get the valoration for display
            $valorationIcon = 'ti-star-filled text-warning';
            $valorationText = 'Top';
            if ($collaborator->valoration) {
                switch($collaborator->valoration->name) {
                    case 'Top':
                        $valorationIcon = 'ti-star-filled text-warning';
                        $valorationText = 'Top';
                        break;
                    case 'Validada':
                        $valorationIcon = 'ti-check text-success';
                        $valorationText = 'Validada';
                        break;
                    case 'Interesante':
                        $valorationIcon = 'ti-clock text-info';
                        $valorationText = 'Interesante';
                        break;
                    case 'Ojo':
                        $valorationIcon = 'ti-eye text-warning';
                        $valorationText = 'Ojo';
                        break;
                    case 'Lista negra':
                        $valorationIcon = 'ti-x text-danger';
                        $valorationText = 'Lista negra';
                        break;
                }
            } else {
                // No valoration assigned
                $valorationIcon = 'ti-minus text-muted';
                $valorationText = 'Sin valoración';
            }

            // Get primary language combination for display
            $primaryLanguage = '';
            if ($collaborator->languageVariants->count() > 0) {
                $firstVariant = $collaborator->languageVariants->first();
                $sourceLang = $firstVariant->sourceLanguage ? $firstVariant->sourceLanguage->name : $firstVariant->source_language_code;
                $targetLang = $firstVariant->targetLanguage ? $firstVariant->targetLanguage->name : $firstVariant->target_language_code;
                $primaryLanguage = $sourceLang . ' → ' . $targetLang;
            }

            // Get primary service for display
            $primaryService = '';
            if ($collaborator->fares->count() > 0) {
                $firstFare = $collaborator->fares->first();
                $primaryService = $firstFare->name ?? 'N/A';
                if ($firstFare->type) {
                    $primaryService .= ' (' . $firstFare->type->name . ')';
                }
            }

            // Check if collaborator was removed from project
            $isRemoved = $collaborator->pivot->deleted_at !== null;

            // Get message status
            $messageStatus = $collaborator->pivot->status ?? 'sent';
            $messageStatusClass = [
                'sent' => 'bg-label-info',
                'viewed' => 'bg-label-warning',
                'accepted' => 'bg-label-success',
                'rejected' => 'bg-label-danger'
            ][$messageStatus] ?? 'bg-label-secondary';
        @endphp

        <div class="col-md-6 mb-3 d-flex">
            <div class="card border w-100 {{ $isRemoved ? 'bg-light opacity-75' : '' }}">
                <div class="card-body p-3 position-relative">
                    <!-- Dropdown Menu in top right corner -->
                    <div class="position-absolute top-0 end-0 mt-2 me-2">
                        <div class="dropdown">
                            <button class="btn p-0" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="ti ti-dots-vertical ti-sm text-muted"></i>
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="{{ route('collaborator.show', $collaborator->id) }}">
                                    <i class="ti ti-eye me-2"></i>{{ __('View Details') }}
                                </a>
                                @if(!$isRemoved)
                                <a class="dropdown-item text-danger" href="javascript:void(0)"
                                   onclick="removeCollaboratorFromProject({{ $project->id }}, {{ $collaborator->id }}, '{{ $collaborator->name }}')">
                                    <i class="ti ti-trash me-2"></i>{{ __('Remove from Project') }}
                                </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Collaborator Info -->
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md me-3">
                            <span class="avatar-initial rounded-circle bg-label-{{ ['primary', 'success', 'info', 'warning', 'danger'][($index % 5)] }}">{{ strtoupper(substr($collaborator->name, 0, 2)) }}</span>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $collaborator->name }}</h6>
                            @if($primaryService)
                                <small class="text-muted">{{ $primaryService }}</small>
                            @endif
                            <small class="text-muted d-block">{{ $primaryLanguage }}</small>
                            <div class="d-flex align-items-center mt-1">
                                <i class="ti {{ $valorationIcon }} ti-xs me-1"></i>
                                <small class="text-muted">{{ $valorationText }}</small>
                                @if($isRemoved)
                                    <span class="badge bg-secondary ms-2">Eliminado</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endif

<!-- Linked Services Section - Full Width, same style as details -->
@if($project->projectFares && $project->projectFares->count() > 0)
<div class="card mb-4">
   <div class="card-header d-flex justify-content-between align-items-center">
       <h5 class="mb-0">{{ __('Linked services') }}</h5>
       @can('update', $project)
       <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#serviceModal">
           <i class="ti ti-plus ti-xs me-1"></i>{{ __('Vincular servicio') }}
       </button>
       @endcan
   </div>
   <div class="card-body">
       <div class="table-responsive">
           <table class="table table-hover">
					<thead>
						<tr>
							<th class="col-1"></th>
							<th class="col-3">{{ __('Service') }}</th>
							<th class="col-3 text-center">{{ __('Languages') }}</th>
							<th class="col-1 text-center">{{ __('Quantity') }}</th>
							<th class="col-1">{{ __('Unit') }}</th>
							<th class="col-2 text-center">{{ __('Collaborators') }}</th>
							<th class="col-1 text-center">{{ __('Actions') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach($project->projectFares as $projectFare)
						@php
							// Check if there are collaborators that match this service requirements
							$hasMatchingCollaborator = false;

							if ($project->allCollaborators && $project->allCollaborators->count() > 0) {
								foreach ($project->allCollaborators as $collaborator) {
									// Check if collaborator has the required language combination
									$hasLanguageCombination = $collaborator->languageVariants->contains(function($variant) use ($projectFare) {
										return $variant->source_language_code === $projectFare->source_language_code
											&& $variant->target_language_code === $projectFare->target_language_code;
									});

									// Check if collaborator has the required fare/service
									$hasFare = $collaborator->fares->contains('id', $projectFare->fare_id);

									// If collaborator has both requirements, mark as matching
									if ($hasLanguageCombination && $hasFare) {
										$hasMatchingCollaborator = true;
										break;
									}
								}
							}
						@endphp
						<tr class="{{ $loop->last ? 'border-bottom-0' : '' }}">
							<td class="col-1 text-center">
								@if($hasMatchingCollaborator)
									<i class="ti ti-check text-success ti-lg"
									   data-bs-toggle="tooltip"
									   data-bs-placement="top"
									   title="Hay colaboradores asignados que cumplen con los requisitos"></i>
								@else
									<i class="ti ti-alert-triangle text-warning ti-lg"
									   data-bs-toggle="tooltip"
									   data-bs-placement="top"
									   title="No hay colaboradores asignados que cumplan con esta combinación de idioma y servicio"></i>
								@endif
							</td>
							<td class="col-3">
								<div class="mb-1">
									<strong>{{ $projectFare->fare->name ?? 'N/A' }}</strong>
									@if($projectFare->fare && $projectFare->fare->type)
										<br><small class="text-muted">{{ $projectFare->fare->type->name }}</small>
									@endif
								</div>
							</td>
							<td class="col-3 text-center">
								<x-language-combination-badge
									:sourceLanguage="$projectFare->sourceLanguage"
									:targetLanguage="$projectFare->targetLanguage"
									:sourceLanguageCode="$projectFare->source_language_code"
									:targetLanguageCode="$projectFare->target_language_code"
								/>
							</td>
							<td class="col-1 text-center">
								<span class="badge bg-light text-dark">{{ $projectFare->quantity }}</span>
							</td>
							<td class="col-1">
								{{ $projectFare->unit }}
							</td>
							<td class="col-2 text-center">
								<a href="{{ route('project.select-collaborators', $project->id) }}?source_language={{ $projectFare->source_language_code }}&target_language={{ $projectFare->target_language_code }}&service={{ $projectFare->fare_id }}"
								   class="btn btn-sm btn-outline-success">
									<i class="ti ti-users ti-xs me-1"></i>Asociar
								</a>
							</td>
							<td class="col-1 text-center">
                                <div class="d-flex justify-content-center align-items-center">
                                    @can('update', $project)
                                    <a href="javascript:;" class="text-body me-2" data-bs-toggle="modal" data-bs-target="#serviceModal" data-action="edit" data-service-id="{{ $projectFare->id }}">
                                        <i class="ti ti-edit ti-sm"></i>
                                    </a>
                                    @endcan
                                    @can('delete', $project)
                                    <a href="javascript:;" class="text-danger" onclick="deleteProjectService({{ $projectFare->id }})">
                                        <i class="ti ti-trash ti-sm"></i>
                                    </a>
                                    @endcan
                                </div>
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
	@else
	<div class="card mb-4">
		<div class="card-body text-center py-4">
			<i class="ti ti-settings ti-xl text-muted mb-3"></i>
			<h6 class="mb-2">{{ __('No linked services') }}</h6>
			<p class="text-muted mb-3">{{ __('This project has no linked services yet') }}</p>
		@can('update', $project)
		<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#serviceModal">
			<i class="ti ti-plus me-1"></i>{{ __('Vincular servicio') }}
		</button>
		@endcan
		</div>
	</div>
	@endif

<!-- Modal para agregar/editar servicio -->
<div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="serviceModalTitle">Agregar servicio</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form id="serviceForm">
				<div class="modal-body">
					<input type="hidden" id="service-id" name="service_id">
					<input type="hidden" id="project-id" name="project_id" value="{{ $project->id }}">

					<div class="row g-3">
						<div class="col-md-6">
							<x-variant-language-select
								name="source_language_code"
								id="source_language"
								label="Idioma origen (*)"
								:required="true"
								placeholder="Seleccionar idioma origen"
							/>
						</div>
						<div class="col-md-6">
							<x-variant-language-select
								name="target_language_code"
								id="target_language"
								label="Idioma destino (*)"
								:required="true"
								placeholder="Seleccionar idioma destino"
							/>
						</div>
						<div class="col-md-8">
							<label class="form-label">Tipo de servicio (*)</label>
							<select name="fare_id" id="fare_select" class="form-select" required>
								<option value="">Seleccionar servicio</option>
								@php
									$faresByType = \App\Models\Fare::with('type')
										->where(function($query) {
											$query->whereNull('team_id');
											if (auth()->check() && auth()->user()->currentTeam) {
												$query->orWhere('team_id', auth()->user()->currentTeam->id);
											}
										})
										->orderBy('name')
										->get()
										->groupBy(function($fare) {
											return $fare->type ? $fare->type->name : 'Sin categoría';
										});
								@endphp

								@foreach($faresByType as $typeName => $fareList)
									<optgroup label="{{ $typeName }}">
										@foreach($fareList as $fare)
											<option value="{{ $fare->id }}">{{ $fare->name }}</option>
										@endforeach
									</optgroup>
								@endforeach
							</select>
						</div>
						<div class="col-md-4">
							<label class="form-label">Cantidad (*)</label>
							<input type="number" name="quantity" id="quantity" class="form-control"
								   value="1" min="1" step="1" required>
						</div>
						<div class="col-md-6">
							<label class="form-label">Unidad (*)</label>
							<select name="unit" id="unit_select" class="form-select" required>
								<option value="">Primero selecciona un servicio</option>
							</select>
						</div>
					</div>

					<div class="mt-3">
						<div class="alert alert-warning d-none" id="duplicate-warning">
							<i class="ti ti-alert-triangle me-2"></i>
							Este servicio ya está agregado con la misma combinación de idiomas.
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary" id="saveServiceBtn">
						<i class="ti ti-check me-1"></i>
						<span id="saveServiceText">Agregar servicio</span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

@endsection

@push('scripts')
<script>
	// Initialize tooltips
	document.addEventListener('DOMContentLoaded', function() {
		var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
		var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
			return new bootstrap.Tooltip(tooltipTriggerEl);
		});

		// Service modal functionality
		initializeServiceModal();
	});

	// Function to remove collaborator from project
	function removeCollaboratorFromProject(projectId, collaboratorId, collaboratorName) {
		Swal.fire({
			title: '¿Estás seguro?',
			text: `¿Deseas eliminar a ${collaboratorName} de este proyecto?`,
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
					url: `/project/${projectId}/remove-collaborator/${collaboratorId}`,
					type: 'DELETE',
					data: {
						_token: $('meta[name="csrf-token"]').attr('content'),
					},
					success: function (response) {
						toastr.success('Colaborador eliminado del proyecto exitosamente');
						// Reload the page to update the collaborator list
						setTimeout(() => {
							location.reload();
						}, 1000);
					},
					error: function (response) {
						Swal.fire({
							title: 'Error',
							text: response.responseJSON?.message || 'Ha ocurrido un error',
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

	// Function to delete project service
	function deleteProjectService(serviceId) {
		if (!confirm('¿Estás seguro de que deseas eliminar este servicio?')) {
			return;
		}

		const projectId = document.getElementById('project-id').value;

		fetch(`/project/${projectId}/service/${serviceId}`, {
			method: 'DELETE',
			headers: {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
			}
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				location.reload();
			} else {
				alert(data.message || 'Error al eliminar servicio');
			}
		})
		.catch(error => {
			console.error('Error deleting service:', error);
			alert('Error al eliminar servicio');
		});
	}

	// Service modal initialization
	function initializeServiceModal() {
		const serviceModal = document.getElementById('serviceModal');
		const serviceForm = document.getElementById('serviceForm');
		const duplicateWarning = document.getElementById('duplicate-warning');

		let editingServiceId = null;

		// Handle fare selection change for units
		document.getElementById('fare_select').addEventListener('change', function() {
			const fareId = this.value;
			const unitSelect = document.getElementById('unit_select');

			if (!fareId) {
				unitSelect.innerHTML = '<option value="">Primero selecciona un servicio</option>';
				return;
			}

			// Show loading state
			unitSelect.innerHTML = '<option value="">Cargando unidades...</option>';
			unitSelect.disabled = true;

			// Fetch units for the selected fare
			fetch(`/project/fare-units?fare_id=${fareId}`, {
				method: 'GET',
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest'
				}
			})
			.then(response => response.json())
			.then(data => {
				unitSelect.innerHTML = '<option value="">Seleccionar unidad</option>';

				if (data.units && data.units.length > 0) {
					data.units.forEach(unit => {
						const option = document.createElement('option');
						option.value = unit.type;
						option.textContent = unit.label;
						unitSelect.appendChild(option);
					});
				} else {
					unitSelect.innerHTML = '<option value="">No hay unidades disponibles</option>';
				}
			})
			.catch(error => {
				console.error('Error loading units:', error);
				unitSelect.innerHTML = '<option value="">Error al cargar unidades</option>';
			})
			.finally(() => {
				unitSelect.disabled = false;
			});
		});

		// Handle form submission
		serviceForm.addEventListener('submit', function(e) {
			e.preventDefault();

			const formData = new FormData(serviceForm);
			const serviceData = {
				service_id: formData.get('service_id'),
				project_id: formData.get('project_id'),
				fare_id: formData.get('fare_id'),
				source_language_code: formData.get('source_language_code'),
				target_language_code: formData.get('target_language_code'),
				quantity: formData.get('quantity'),
				unit: formData.get('unit')
			};

			duplicateWarning.classList.add('d-none');

			// Determine if we're editing or adding
			if (editingServiceId) {
				updateService(serviceData);
			} else {
				addService(serviceData);
			}
		});

		// Handle modal show event
		serviceModal.addEventListener('show.bs.modal', function(event) {
			const button = event.relatedTarget;
			const action = button?.getAttribute('data-action');

			if (action === 'edit') {
				const serviceId = button.getAttribute('data-service-id');
				editService(serviceId);
			} else {
				// Reset form for new service
				resetForm();
			}
		});

		// Handle modal hide event
		serviceModal.addEventListener('hide.bs.modal', function() {
			resetForm();
		});

		function addService(serviceData) {
			const button = document.getElementById('saveServiceBtn');
			const originalText = button.innerHTML;

			button.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Guardando...';
			button.disabled = true;

			fetch(`/project/${serviceData.project_id}/service`, {
				method: 'POST',
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
				},
				body: JSON.stringify(serviceData)
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					// Close modal and reload page
					const modal = bootstrap.Modal.getInstance(serviceModal);
					modal.hide();
					location.reload();
				} else {
					alert(data.message || 'Error al agregar servicio');
				}
			})
			.catch(error => {
				console.error('Error adding service:', error);
				alert('Error al agregar servicio');
			})
			.finally(() => {
				button.innerHTML = originalText;
				button.disabled = false;
			});
		}

		function updateService(serviceData) {
			const button = document.getElementById('saveServiceBtn');
			const originalText = button.innerHTML;

			button.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Actualizando...';
			button.disabled = true;

			fetch(`/project/${serviceData.project_id}/service/${serviceData.service_id}`, {
				method: 'PUT',
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
				},
				body: JSON.stringify(serviceData)
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					// Close modal and reload page
					const modal = bootstrap.Modal.getInstance(serviceModal);
					modal.hide();
					location.reload();
				} else {
					alert(data.message || 'Error al actualizar servicio');
				}
			})
			.catch(error => {
				console.error('Error updating service:', error);
				alert('Error al actualizar servicio');
			})
			.finally(() => {
				button.innerHTML = originalText;
				button.disabled = false;
			});
		}

		function editService(serviceId) {
			const projectId = document.getElementById('project-id').value;

			// Get service details from server
			fetch(`/project/${projectId}/services`, {
				method: 'GET',
				headers: {
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest'
				}
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					const service = data.services.find(s => s.id == serviceId);
					if (service) {
						editingServiceId = serviceId;

						// Update modal title
						document.getElementById('serviceModalTitle').textContent = 'Editar servicio';
						document.getElementById('saveServiceText').textContent = 'Actualizar servicio';

						// Fill form with service data
						document.getElementById('service-id').value = service.id;
						document.getElementById('source_language').value = service.source_language_code;
						document.getElementById('target_language').value = service.target_language_code;
						document.getElementById('fare_select').value = service.fare_id;
						document.getElementById('quantity').value = service.quantity;

						// Load units for the selected fare
						const fareSelect = document.getElementById('fare_select');
						fareSelect.dispatchEvent(new Event('change'));

						// Set unit after units are loaded
						setTimeout(() => {
							document.getElementById('unit_select').value = service.unit;
						}, 500);
					}
				}
			})
			.catch(error => {
				console.error('Error loading service:', error);
			});
		}

		function resetForm() {
			serviceForm.reset();
			editingServiceId = null;
			document.getElementById('service-id').value = '';
			document.getElementById('serviceModalTitle').textContent = 'Agregar servicio';
			document.getElementById('saveServiceText').textContent = 'Agregar servicio';
			document.getElementById('unit_select').innerHTML = '<option value="">Primero selecciona un servicio</option>';
			duplicateWarning.classList.add('d-none');
		}
	}
</script>
@endpush
