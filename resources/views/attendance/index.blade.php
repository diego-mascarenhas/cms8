@extends('layouts/layoutMaster')

@section('title', __('Work shifts'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3">{{ __('Work shifts') }}</h4>
		<p class="text-muted">{{ __('Clock-in / clock-out history') }}</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3 mt-3 mt-md-0">
		<a href="{{ route('time.index') }}" class="btn btn-label-secondary">
			<i class="ti ti-clock me-1"></i> {{ __('Times') }}
		</a>
	</div>
</div>

<div class="card">
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th>{{ __('User') }}</th>
						<th>{{ __('Start') }}</th>
						<th>{{ __('End') }}</th>
						<th>{{ __('Duration') }}</th>
						<th>{{ __('Status') }}</th>
					</tr>
				</thead>
				<tbody>
					@forelse($attendances as $attendance)
						<tr>
							<td>{{ $attendance->user?->name ?? '-' }}</td>
							<td>{{ $attendance->start_at?->format('d/m/Y H:i') }}</td>
							<td>{{ $attendance->end_at ? $attendance->end_at->format('d/m/Y H:i') : '-' }}</td>
							<td>
								@php
									$seconds = null;
									if ($attendance->start_at && $attendance->end_at) {
										$total = $attendance->end_at->getTimestamp() - $attendance->start_at->getTimestamp();
										$paused = (int) ($attendance->paused_seconds ?? 0);
										$seconds = max(0, (int) $total - $paused);
									} else {
										$seconds = $attendance->duration_seconds !== null && $attendance->duration_seconds !== '' ? (int) $attendance->duration_seconds : null;
									}
								@endphp
								@if($seconds !== null)
									{{ (int) floor($seconds / 3600) }}h {{ (int) floor(($seconds % 3600) / 60) }}m
								@else
									-
								@endif
							</td>
							<td>
								@if($attendance->isRunning())
									@if($attendance->paused_at)
										<span class="badge bg-warning">{{ __('Paused') }}</span>
									@else
										<span class="badge bg-success">{{ __('In progress') }}</span>
									@endif
								@else
									<span class="badge bg-label-secondary">{{ __('Finished') }}</span>
								@endif
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="5" class="text-center text-muted py-4">{{ __('No work shifts recorded.') }}</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
		@if($attendances->hasPages())
			<div class="d-flex justify-content-center mt-3">
				{{ $attendances->links() }}
			</div>
		@endif
	</div>
</div>
@endsection
