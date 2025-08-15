@extends('layouts/layoutMaster')

@section('title', 'Message Detail')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3">
			<span class="text-muted fw-light">Messages /</span> {{ $message->name }}
		</h4>
		<p class="text-muted">Detailed view of the message and its statistics</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3">
		<a href="{{ route('message-list') }}" class="btn btn-label-secondary">
			<i class="ti ti-arrow-left me-1"></i>Back to list
		</a>
	</div>
</div>

<div class="row">
	<!-- General Info -->
	<div class="col-md-4">
		<div class="card mb-4">
			<div class="card-header">General Information</div>
			<div class="card-body">
				<div class="mb-2"><strong>Subject:</strong> {{ $message->name }}</div>
				<div class="mb-2"><strong>Sender:</strong> {{ $emailConfig['from_name'] ?? 'Not configured' }}</div>
				<div class="mb-2"><strong>Sender Email:</strong> {{ $emailConfig['from_address'] ?? 'Not configured' }}</div>
								<div class="mb-2"><strong>Category:</strong>
					@if($message->category)
						{{ $message->category->name }}
					@else
						All contacts
					@endif
				</div>
			</div>
		</div>
	</div>
	<!-- Progress & Status -->
	<div class="col-md-8">
		<div class="card mb-4">
			<div class="card-header">Message Progress</div>
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-2">
					<div>
						<span class="fw-bold">Sent:</span> {{ $stats['sent'] }}
						<span class="ms-3 fw-bold">Delivered:</span> {{ $stats['delivered'] }}
						<span class="ms-3 fw-bold">Failed:</span> {{ $stats['failed'] }}
					</div>
					<span class="badge bg-success">Completed</span>
				</div>
				<div class="progress mb-2" style="height: 20px;">
					<div class="progress-bar bg-success" style="width: 100%;">100%</div>
				</div>
				<div>
					<button class="btn btn-label-primary me-2"><i class="ti ti-eye"></i> Preview</button>
					<button class="btn btn-label-secondary me-2"><i class="ti ti-copy"></i> Duplicate</button>
					<button class="btn btn-label-success"><i class="ti ti-send"></i> Send Now</button>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<!-- Delivery Stats Component -->
	<div class="col-md-4">
		<x-delivery-stats :stats="$stats_db" />
	</div>
	<!-- Deliveries Table wider -->
	<div class="col-md-8">
		<div class="card mb-4">
			<div class="card-header">Deliveries</div>
			<div class="card-body table-responsive">
				<table class="table table-sm">
					<thead>
						<tr>
							<th>Contact</th>
							<th>SMTP ID</th>
							<th>Sent At</th>
							<th>Delivered At</th>
							<th>Removed At</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>
						@foreach($deliveries as $delivery)
							<tr>
								<td>{{ $delivery->contact ? $delivery->contact->name : '-' }}</td>
								<td>{{ $delivery->smtp_id ?? '-' }}</td>
								<td>
									{{ $delivery->sent_at ? $delivery->sent_at : 'Pending' }}
								</td>
								<td>
									{{ $delivery->delivered_at ?? '-' }}
								</td>
								<td>
									{{ $delivery->removed_at ?? '-' }}
								</td>
								<td>
									@if(is_null($delivery->sent_at))
										<span class="badge bg-warning">Pending</span>
									@else
										<span class="badge bg-success">{{ ucfirst($delivery->status) }}</span>
									@endif
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-12">
		<div class="card mb-4">
			<div class="card-header">Lead Conversion Links</div>
			<div class="card-body table-responsive">
				<table class="table table-sm">
					<thead>
						<tr>
							<th>Delivery</th>
							<th>Created At</th>
							<th>Link</th>
						</tr>
					</thead>
					<tbody>
						@foreach($links as $link)
							<tr>
								<td>{{ $link->message_delivery_id }}</td>
								<td>{{ $link->created_at }}</td>
								<td>
									<a href="{{ $link->link }}" target="_blank">{{ $link->link }}</a>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection
