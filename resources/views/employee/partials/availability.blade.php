<div class="card">
	<div class="card-header">
		<h5 class="card-title mb-0">Availability Information</h5>
	</div>
	<div class="card-body">
		<div class="row">
			<div class="col-md-6">
				<h6>Weekly Availability</h6>
				@if($contact->weeklyAvailability)
					<div class="list-group list-group-flush">
						<div class="list-group-item d-flex justify-content-between align-items-center">
							<span>Monday</span>
							<span class="badge {{ $contact->weeklyAvailability->monday ? 'bg-label-success' : 'bg-label-danger' }}">
								{{ $contact->weeklyAvailability->monday ? 'Available' : 'Not Available' }}
							</span>
						</div>
						<div class="list-group-item d-flex justify-content-between align-items-center">
							<span>Tuesday</span>
							<span class="badge {{ $contact->weeklyAvailability->tuesday ? 'bg-label-success' : 'bg-label-danger' }}">
								{{ $contact->weeklyAvailability->tuesday ? 'Available' : 'Not Available' }}
							</span>
						</div>
						<div class="list-group-item d-flex justify-content-between align-items-center">
							<span>Wednesday</span>
							<span class="badge {{ $contact->weeklyAvailability->wednesday ? 'bg-label-success' : 'bg-label-danger' }}">
								{{ $contact->weeklyAvailability->wednesday ? 'Available' : 'Not Available' }}
							</span>
						</div>
						<div class="list-group-item d-flex justify-content-between align-items-center">
							<span>Thursday</span>
							<span class="badge {{ $contact->weeklyAvailability->thursday ? 'bg-label-success' : 'bg-label-danger' }}">
								{{ $contact->weeklyAvailability->thursday ? 'Available' : 'Not Available' }}
							</span>
						</div>
						<div class="list-group-item d-flex justify-content-between align-items-center">
							<span>Friday</span>
							<span class="badge {{ $contact->weeklyAvailability->friday ? 'bg-label-success' : 'bg-label-danger' }}">
								{{ $contact->weeklyAvailability->friday ? 'Available' : 'Not Available' }}
							</span>
						</div>
						<div class="list-group-item d-flex justify-content-between align-items-center">
							<span>Saturday</span>
							<span class="badge {{ $contact->weeklyAvailability->saturday ? 'bg-label-success' : 'bg-label-danger' }}">
								{{ $contact->weeklyAvailability->saturday ? 'Available' : 'Not Available' }}
							</span>
						</div>
						<div class="list-group-item d-flex justify-content-between align-items-center">
							<span>Sunday</span>
							<span class="badge {{ $contact->weeklyAvailability->sunday ? 'bg-label-success' : 'bg-label-danger' }}">
								{{ $contact->weeklyAvailability->sunday ? 'Available' : 'Not Available' }}
							</span>
						</div>
					</div>
				@else
					<p class="text-muted">No weekly availability configured.</p>
				@endif
			</div>

			<div class="col-md-6">
				<h6>Absences (Current Year)</h6>
				@if($contact->absences->count() > 0)
					<div class="list-group list-group-flush">
						@foreach($contact->absences->where('date', '>=', now()->startOfYear())->take(10) as $absence)
							<div class="list-group-item d-flex justify-content-between align-items-center">
								<span>{{ \Carbon\Carbon::parse($absence->date)->format('d/m/Y') }}</span>
								<span class="badge bg-label-warning">Absence</span>
							</div>
						@endforeach
						@if($contact->absences->where('date', '>=', now()->startOfYear())->count() > 10)
							<div class="list-group-item text-center text-muted">
								<small>And {{ $contact->absences->where('date', '>=', now()->startOfYear())->count() - 10 }} more...</small>
							</div>
						@endif
					</div>
				@else
					<p class="text-muted">No absences recorded for this year.</p>
				@endif
			</div>
		</div>

		<div class="row mt-3">
			<div class="col-12">
				<a href="{{ route('employee.absences', $contact->id) }}" class="btn btn-primary">
					<i class="ti ti-calendar me-1"></i>Manage Availability
				</a>
			</div>
		</div>
	</div>
</div>
