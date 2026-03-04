@extends('layouts/layoutMaster')

@section('title', $task->title ?? __('Task'))

@section('content')
	<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
		<div class="d-flex flex-column justify-content-center">
			<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Tasks') }}/</span> {{ $task->title ?? __('Task') }}</h4>
			<p class="text-muted mb-0">
				@if($task->status)
					<span class="badge rounded-pill {{ $task->status->label_class ?? 'bg-label-secondary' }}">{{ $task->status->translated_name ?? $task->status->name }}</span>
				@endif
				@if($task->board_id && $task->project)
					<span class="ms-2">{{ __('Project') }}: <a href="{{ route('project.show', $task->project->id) }}">{{ $task->project->real_name ?? $task->project->name }}</a></span>
				@endif
			</p>
		</div>
		<div class="d-flex align-content-center flex-wrap gap-3 mt-3 mt-md-0">
			@can('update', $task)
				<a href="{{ route('task.edit', $task->id) }}" class="btn btn-primary waves-effect waves-light">
					<i class="ti ti-edit me-1"></i>{{ __('Edit') }}
				</a>
			@endcan
			<a href="{{ route('task.index') }}" class="btn btn-label-secondary waves-effect waves-light">
				<i class="ti ti-arrow-left me-1"></i>{{ __('Back to Tasks') }}
			</a>
			@if($task->board_id && $task->project)
				<a href="{{ route('task.index', ['view' => 'kanban', 'project_id' => $task->project->id]) }}" class="btn btn-info waves-effect waves-light">
					<i class="ti ti-layout-kanban me-1"></i>{{ __('Kanban Board') }}
				</a>
			@endif
		</div>
	</div>

	<div class="card mb-4">
		<div class="card-header">
			<h5 class="mb-0">{{ __('Task Details') }}</h5>
		</div>
		<div class="card-body">
			<div class="row">
				<div class="col-md-6">
					<dl class="row mb-0">
						<dt class="col-4 text-truncate">{{ __('Status') }}:</dt>
						<dd class="col-8">
							@if($task->status)
								<span class="badge rounded-pill {{ $task->status->label_class ?? 'bg-label-secondary' }}">{{ $task->status->translated_name ?? $task->status->name }}</span>
							@else
								—
							@endif
						</dd>

						<dt class="col-4 text-truncate">{{ __('Category') }}:</dt>
						<dd class="col-8">{{ $task->category ? $task->category->name : '—' }}</dd>

						<dt class="col-4 text-truncate">{{ __('Responsible') }}:</dt>
						<dd class="col-8">{{ $task->responsible ? $task->responsible->name : '—' }}</dd>

						<dt class="col-4 text-truncate">{{ __('Estimated (h)') }}:</dt>
						<dd class="col-8">{{ $task->estimated_hours !== null && $task->estimated_hours !== '' ? number_format((float) $task->estimated_hours, 1) : '—' }}</dd>
					</dl>
				</div>
				<div class="col-md-6">
					<dl class="row mb-0">
						<dt class="col-4 text-truncate">{{ __('Start Date') }}:</dt>
						<dd class="col-8">
							@if(isset($actualStartAt) && $actualStartAt)
								{{ \Carbon\Carbon::parse($actualStartAt)->format('d/m/Y H:i') }}
							@else
								{{ $task->start_date ? \Carbon\Carbon::parse($task->start_date)->format('d/m/Y') : '—' }}
							@endif
						</dd>

						<dt class="col-4 text-truncate">{{ __('Due Date') }}:</dt>
						<dd class="col-8">
							@if(isset($actualEndAt) && $actualEndAt)
								{{ \Carbon\Carbon::parse($actualEndAt)->format('d/m/Y H:i') }}
							@else
								{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('d/m/Y') : '—' }}
							@endif
						</dd>
					</dl>
				</div>
			</div>
			@if($task->description)
				<hr class="my-3">
				<dt class="text-muted small">{{ __('Description') }}</dt>
				<dd class="mb-0">{!! nl2br(e($task->description)) !!}</dd>
			@endif
		</div>
	</div>
@endsection
