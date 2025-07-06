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
			@can('project.edit')
				<a href="{{ route('project.edit', $project->id) }}" class="btn btn-primary waves-effect waves-light">
					<i class="ti ti-edit me-1"></i>{{ __('Edit Project') }}
				</a>
			@endcan
			<a href="{{ route('project.select-collaborators', $project->id) }}" class="btn btn-success waves-effect waves-light">
				<i class="ti ti-users me-1"></i>{{ __('Manage Collaborators') }}
			</a>
			<a href="{{ route('project.add-services', $project->id) }}" class="btn btn-info waves-effect waves-light">
				<i class="ti ti-settings me-1"></i>{{ __('Link services') }}
			</a>
			@can('project.index')
				<a href="{{ route('project-list') }}" class="btn btn-label-secondary waves-effect waves-light"><i class="ti ti-arrow-left me-1"></i>{{ __('Back to Projects') }}</a>
			@endcan
		</div>
	</div>

	<div class="row">
		<!-- Main Dashboard Content -->
		<div class="col-xl-8 col-lg-8">

			<!-- Project Details Card -->
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

			<!-- Linked Services Section -->
			@if($project->projectFares && $project->projectFares->count() > 0)
			<div class="card mb-4">
				<div class="card-header d-flex justify-content-between align-items-center">
					<h5 class="mb-0">{{ __('Linked services') }}</h5>
					<a href="{{ route('project.add-services', $project->id) }}" class="btn btn-sm btn-outline-primary">
						<i class="ti ti-edit ti-xs me-1"></i>{{ __('Edit services') }}
					</a>
				</div>
				<div class="card-body">
					@foreach($project->projectFares as $projectFare)
					@php
						// Check if there are collaborators that match this service requirements
						$hasMatchingCollaborator = false;
						
						if ($project->collaborators && $project->collaborators->count() > 0) {
							foreach ($project->collaborators as $collaborator) {
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
					<div class="row align-items-center py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
						<div class="col-auto">
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
						</div>
						<div class="col">
							<div class="row">
								<div class="col-md-4">
									<div class="mb-1">
										<strong>{{ $projectFare->fare->name ?? 'N/A' }}</strong>
										@if($projectFare->fare && $projectFare->fare->type)
											<br><small class="text-muted">{{ $projectFare->fare->type->name }}</small>
										@endif
									</div>
								</div>
								<div class="col-md-5">
									<x-language-combination-badge 
										:sourceLanguage="$projectFare->sourceLanguage"
										:targetLanguage="$projectFare->targetLanguage"
										:sourceLanguageCode="$projectFare->source_language_code"
										:targetLanguageCode="$projectFare->target_language_code"
									/>
								</div>
								<div class="col-md-3 text-end">
									<span class="badge bg-light text-dark">{{ $projectFare->quantity }} {{ $projectFare->unit }}</span>
								</div>
							</div>
						</div>
					</div>
					@endforeach
				</div>
			</div>
			@else
			<div class="card mb-4">
				<div class="card-body text-center py-4">
					<i class="ti ti-settings ti-xl text-muted mb-3"></i>
					<h6 class="mb-2">{{ __('No linked services') }}</h6>
					<p class="text-muted mb-3">{{ __('This project has no linked services yet') }}</p>
					<a href="{{ route('project.add-services', $project->id) }}" class="btn btn-primary">
						<i class="ti ti-plus me-1"></i>{{ __('Link services') }}
					</a>
				</div>
			</div>
			@endif

			<!-- Collaborators Section (Floating Cards) -->
			@if($project->collaborators && $project->collaborators->count() > 0)
			<div class="row mb-4" style="align-items: stretch;">
				@foreach($project->collaborators as $index => $collaborator)
					@php
						// Get the valoration for display
						$valorationIcon = 'ti-star-filled text-warning';
						$valorationText = 'Top';
						if ($collaborator->valoration) {
							switch($collaborator->valoration->name) {
								case 'Lista negra':
									$valorationIcon = 'ti-x text-danger';
									$valorationText = 'Lista negra';
									break;
								case 'Validada':
									$valorationIcon = 'ti-check text-success';
									$valorationText = 'Validada';
									break;
								case 'En espera':
									$valorationIcon = 'ti-eye text-warning';
									$valorationText = 'Ojo';
									break;
								case 'Interesante':
									$valorationIcon = 'ti-clock text-info';
									$valorationText = 'Interesante';
									break;
							}
						}
						
						// Get primary language combination for display
						$primaryLanguage = '';
						if ($collaborator->languageVariants->count() > 0) {
							$firstVariant = $collaborator->languageVariants->first();
							$sourceLang = $firstVariant->sourceLanguage ? $firstVariant->sourceLanguage->name : $firstVariant->source_language_code;
							$targetLang = $firstVariant->targetLanguage ? $firstVariant->targetLanguage->name : $firstVariant->target_language_code;
							$primaryLanguage = $sourceLang . ' → ' . $targetLang;
						}

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
						<div class="card border w-100">
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
											<a class="dropdown-item text-danger" href="javascript:void(0)" 
											   onclick="removeCollaboratorFromProject({{ $project->id }}, {{ $collaborator->id }}, '{{ $collaborator->name }}')">
												<i class="ti ti-trash me-2"></i>{{ __('Remove from Project') }}
											</a>
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
										<small class="text-muted">{{ $primaryLanguage }}</small>
										<div class="d-flex align-items-center mt-1">
											<i class="ti {{ $valorationIcon }} ti-xs me-1"></i>
											<small class="text-muted">{{ $valorationText }}</small>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				@endforeach
			</div>
			@endif

			<!-- Notes Section -->
			@if($project->notes && $project->notes->count() > 0)
			<div class="card">
				<div class="card-header d-flex justify-content-between">
					<h5 class="mb-0">{{ __('Recent Notes') }}</h5>
					<small class="text-muted">{{ $project->notes->count() }} {{ __('notes') }}</small>
				</div>
				<div class="card-body">
					@foreach($project->notes->take(3) as $note)
						<div class="d-flex mb-3 {{ !$loop->last ? 'pb-3 border-bottom' : '' }}">
							<div class="avatar avatar-sm me-3">
								<span class="avatar-initial rounded-circle bg-label-primary">{{ substr($note->user->name ?? 'U', 0, 1) }}</span>
							</div>
							<div class="flex-grow-1">
								<div class="d-flex justify-content-between align-items-start">
									<div>
										<h6 class="mb-1">{{ $note->user->name ?? __('Unknown User') }}</h6>
										<small class="text-muted">{{ $note->created_at->format('d/m/Y H:i') }}</small>
									</div>
								</div>
								<p class="mb-0 mt-2">{{ $note->content }}</p>
							</div>
						</div>
					@endforeach
					@if($project->notes->count() > 3)
						<div class="text-center mt-3">
							<a href="javascript:void(0)" class="btn btn-label-primary btn-sm">{{ __('View All Notes') }}</a>
						</div>
					@endif
				</div>
			</div>
			@endif
		</div>

		<!-- Timeline Sidebar -->
		<div class="col-xl-4 col-lg-4">
			<div class="card timeline-card">
				<div class="card-header">
					<h5 class="mb-0">{{ __('Project Timeline') }}</h5>
				</div>
				<div class="card-body">
					<ul class="timeline mb-0">
						<!-- Project Created -->
						<li class="timeline-item timeline-item-transparent">
							<span class="timeline-point timeline-point-primary"></span>
							<div class="timeline-event">
								<div class="timeline-header mb-1">
									<h6 class="mb-0">{{ __('Project Created') }}</h6>
									<small class="text-muted">{{ $project->created_at->format('d M Y, H:i') }}</small>
								</div>
								<p class="mb-2">{{ __('Project was created in the system') }}</p>
								<div class="d-flex">
									<div class="avatar avatar-sm me-2">
										<span class="avatar-initial rounded-circle bg-label-primary">{{ substr($project->responsible->name ?? 'U', 0, 1) }}</span>
									</div>
									<div>
										<small class="text-muted">{{ __('by') }} {{ $project->responsible->name ?? __('Unknown') }}</small>
									</div>
								</div>
							</div>
						</li>

						@if($project->date_start)
						<!-- Project Start -->
						<li class="timeline-item timeline-item-transparent">
							<span class="timeline-point timeline-point-info"></span>
							<div class="timeline-event">
								<div class="timeline-header mb-1">
									<h6 class="mb-0">{{ __('Project Start') }}</h6>
									<small class="text-muted">{{ \Carbon\Carbon::parse($project->date_start)->format('d M Y') }}</small>
								</div>
								<p class="mb-0">{{ __('Planned project start date') }}</p>
							</div>
						</li>
						@endif

						@if($project->date_material)
						<!-- Material Delivery -->
						<li class="timeline-item timeline-item-transparent">
							<span class="timeline-point timeline-point-warning"></span>
							<div class="timeline-event">
								<div class="timeline-header mb-1">
									<h6 class="mb-0">{{ __('Material Delivery') }}</h6>
									<small class="text-muted">{{ \Carbon\Carbon::parse($project->date_material)->format('d M Y') }}</small>
								</div>
								<p class="mb-0">{{ __('Materials should be delivered by this date') }}</p>
							</div>
						</li>
						@endif

						<!-- Notes Timeline -->
						@if($project->notes && $project->notes->count() > 0)
							@foreach($project->notes->take(2) as $note)
							<li class="timeline-item timeline-item-transparent">
								<span class="timeline-point timeline-point-success"></span>
								<div class="timeline-event">
									<div class="timeline-header mb-1">
										<h6 class="mb-0">{{ __('Note Added') }}</h6>
										<small class="text-muted">{{ $note->created_at->format('d M Y, H:i') }}</small>
									</div>
									<p class="mb-2">{{ Str::limit($note->content, 100) }}</p>
									<div class="d-flex">
										<div class="avatar avatar-sm me-2">
											<span class="avatar-initial rounded-circle bg-label-success">{{ substr($note->user->name ?? 'U', 0, 1) }}</span>
										</div>
										<small class="text-muted">{{ __('by') }} {{ $note->user->name ?? __('Unknown') }}</small>
									</div>
								</div>
							</li>
							@endforeach
						@endif

						@if($project->date_end)
						<!-- Project End -->
						<li class="timeline-item timeline-item-transparent">
							<span class="timeline-point timeline-point-{{ \Carbon\Carbon::parse($project->date_end)->isPast() ? 'danger' : 'success' }}"></span>
							<div class="timeline-event">
								<div class="timeline-header mb-1">
									<h6 class="mb-0">{{ __('Final Delivery') }}</h6>
									<small class="text-muted">{{ \Carbon\Carbon::parse($project->date_end)->format('d M Y') }}</small>
								</div>
								<p class="mb-0">
									@if(\Carbon\Carbon::parse($project->date_end)->isPast())
										<span class="text-danger">{{ __('Project delivery date has passed') }}</span>
									@else
										{{ __('Planned final delivery date') }}
									@endif
								</p>
							</div>
						</li>
						@endif

						<!-- End marker -->
						<li class="timeline-end-indicator">
							<i class="ti ti-flag"></i>
						</li>
					</ul>
				</div>
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
</script>
@endpush
