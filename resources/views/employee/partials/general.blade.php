<div class="card">
	<div class="card-header">
		<h5 class="card-title mb-0">General Information</h5>
	</div>
	<div class="card-body">
		<div class="row">
			<div class="col-md-6">
				<h6>Personal Information</h6>
				<dl class="row">
					<dt class="col-sm-4">Name:</dt>
					<dd class="col-sm-8">{{ $contact->name }} {{ $contact->surname ?? '' }}</dd>

					<dt class="col-sm-4">Email:</dt>
					<dd class="col-sm-8">{{ $contact->email }}</dd>

					<dt class="col-sm-4">Phone:</dt>
					<dd class="col-sm-8">{{ $contact->phone ?? 'Not provided' }}</dd>

					<dt class="col-sm-4">Birthday:</dt>
					<dd class="col-sm-8">
						@if (isset($contact->birthday))
							{{ \Carbon\Carbon::parse($contact->birthday)->format('d/m/Y') }}
							({{ \Carbon\Carbon::parse($contact->birthday)->age }} years)
						@else
							Not available
						@endif
					</dd>

					<dt class="col-sm-4">DNI:</dt>
					<dd class="col-sm-8">{{ $contact->data->dni ?? 'Not provided' }}</dd>

					<dt class="col-sm-4">Nationality:</dt>
					<dd class="col-sm-8">{{ $contact->data->nationality ?? 'Not provided' }}</dd>
				</dl>
			</div>

			<div class="col-md-6">
				<h6>Work Information</h6>
				<dl class="row">
					<dt class="col-sm-4">Command:</dt>
					<dd class="col-sm-8">{{ $contact->data->command ?? 'Not assigned' }}</dd>

					<dt class="col-sm-4">NAF:</dt>
					<dd class="col-sm-8">{{ $contact->data->naf ?? 'Not provided' }}</dd>

					<dt class="col-sm-4">Contract Type:</dt>
					<dd class="col-sm-8">{{ $contact->data->contract_type ?? 'Not specified' }}</dd>

					<dt class="col-sm-4">Status:</dt>
					<dd class="col-sm-8">
						<span class="badge {{ $contact->status->label_class }}">{{ $contact->status->name }}</span>
					</dd>

					<dt class="col-sm-4">Active:</dt>
					<dd class="col-sm-8">
						@if($contact->data->active ?? true)
							<span class="badge bg-label-success">Active</span>
						@else
							<span class="badge bg-label-danger">Inactive</span>
						@endif
					</dd>

					<dt class="col-sm-4">Responsible:</dt>
					<dd class="col-sm-8">{{ $contact->responsible->name ?? 'Not assigned' }}</dd>
				</dl>
			</div>
		</div>

		<div class="row mt-3">
			<div class="col-12">
				<h6>Address Information</h6>
				<dl class="row">
					<dt class="col-sm-2">Address:</dt>
					<dd class="col-sm-10">{{ $contact->data->address ?? 'Not provided' }}</dd>

					<dt class="col-sm-2">City:</dt>
					<dd class="col-sm-4">{{ $contact->data->city ?? 'Not provided' }}</dd>

					<dt class="col-sm-2">Province:</dt>
					<dd class="col-sm-4">{{ $contact->data->province ?? 'Not provided' }}</dd>

					<dt class="col-sm-2">Postal Code:</dt>
					<dd class="col-sm-4">{{ $contact->data->postal_code ?? 'Not provided' }}</dd>

					<dt class="col-sm-2">Account Number:</dt>
					<dd class="col-sm-4">{{ $contact->data->account_number ?? 'Not provided' }}</dd>
				</dl>
			</div>
		</div>

		@if($contact->profile)
		<div class="row mt-3">
			<div class="col-12">
				<h6>Profile</h6>
				<div class="border rounded p-3">
					{!! nl2br(e($contact->profile)) !!}
				</div>
			</div>
		</div>
		@endif
	</div>
</div>
