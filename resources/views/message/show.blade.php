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
		<!-- Preview Button -->
		<button class="btn btn-primary me-2" onclick="previewMessage()">
			<i class="ti ti-eye me-1"></i>Preview
		</button>
		
		<!-- Send/Pause Toggle Button -->
		@if($message->status_id == 1 && ($stats_db->sent ?? 0) < ($stats_db->subscribers ?? 0))
			<button class="btn btn-warning me-2" onclick="pauseCampaign({{ $message->id }})">
				<i class="ti ti-player-pause me-1"></i>Pause
			</button>
		@else
			<button class="btn btn-success me-2" onclick="startCampaign({{ $message->id }})">
				<i class="ti ti-send me-1"></i>Send Now
			</button>
		@endif
		
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

</div>

<div class="row">
	<!-- Delivery Stats Component (Auto-updating) -->
	<div class="col-md-4">
		@livewire('delivery-stats', ['messageId' => $message->id])
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

@section('page-script')
<script>
function previewMessage() {
    // Open preview in new window/tab
    const previewUrl = `{{ route('message.preview', $message->id ?? 0) }}`;
    window.open(previewUrl, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
}

function startCampaign(messageId) {
    if (confirm('¿Estás seguro de que quieres iniciar el envío de esta campaña?')) {
        fetch(`/message/${messageId}/start`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success notification
                showNotification('Campaña iniciada exitosamente', 'success');
                // Reload page to update button state
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification(data.message || 'Error al iniciar la campaña', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
    }
}

function pauseCampaign(messageId) {
    if (confirm('¿Estás seguro de que quieres pausar esta campaña?')) {
        fetch(`/message/${messageId}/pause`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success notification
                showNotification('Campaña pausada exitosamente', 'success');
                // Reload page to update button state
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification(data.message || 'Error al pausar la campaña', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
    }
}

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}
</script>
@endsection
