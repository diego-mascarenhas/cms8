<div class="card">
	<div class="card-header">
		<h5 class="card-title mb-0">Activity Log</h5>
	</div>
	<div class="card-body">
		@php
			$activities = \Spatie\Activitylog\Models\Activity::where('subject_type', 'App\Models\Contact')
				->where('subject_id', $contact->id)
				->orderBy('created_at', 'desc')
				->take(10)
				->get();
		@endphp

		@if($activities->count() > 0)
			<div class="timeline">
				@foreach($activities as $activity)
					<div class="timeline-item timeline-item-transparent">
						<span class="timeline-point timeline-point-primary"></span>
						<div class="timeline-event">
							<div class="timeline-header mb-1">
								<h6 class="mb-0">{{ ucfirst($activity->description) }}</h6>
								<small class="text-muted">{{ $activity->created_at->format('d/m/Y H:i') }}</small>
							</div>
							@if($activity->changes->count() > 0)
								<div class="timeline-body">
									<small class="text-muted">
										@foreach($activity->changes as $field => $change)
											@if($field !== 'updated_at')
												<strong>{{ ucfirst(str_replace('_', ' ', $field)) }}:</strong>
												@if(is_array($change))
													{{ $change['old'] ?? 'null' }} → {{ $change['new'] ?? 'null' }}
												@else
													{{ $change }}
												@endif
												@if(!$loop->last), @endif
											@endif
										@endforeach
									</small>
								</div>
							@endif
						</div>
					</div>
				@endforeach
			</div>
		@else
			<p class="text-muted text-center">No activity recorded yet.</p>
		@endif

		<div class="text-center mt-3">
			<a href="{{ route('employee.activity', $contact->id) }}" class="btn btn-outline-primary">
				<i class="ti ti-activity me-1"></i>View All Activity
			</a>
		</div>
	</div>
</div>
