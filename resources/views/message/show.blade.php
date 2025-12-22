@extends('layouts/layoutMaster')

@section('title', 'Message Detail')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3">
			<span class="text-muted fw-light">Messages/</span> {{ $message->name }}
		</h4>
		<p class="text-muted">Detailed view of the message and its statistics</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3">
		<!-- Preview Button -->
		<button class="btn btn-outline-primary me-2" onclick="previewMessage()">
			<i class="ti ti-eye me-1"></i>Vista previa
		</button>

		<!-- Send/Pause Toggle Button - Only show if sender is configured -->
		@php
			$isAuthorized = isset($dnsStatus) && $dnsStatus['spf']['has_mailbaby'] && $dnsStatus['mailbaby_auth']['authorized'];
			$usingSystemSmtp = auth()->user()->currentTeam->isUsingSystemSmtp();
			$canSend = !$usingSystemSmtp || $isAuthorized;
		@endphp

		@php
			// Check if campaign is active and has deliveries pending or in progress
			$totalDeliveries = \App\Models\MessageDelivery::where('message_id', $message->id)->count();
			$sentDeliveries = \App\Models\MessageDelivery::where('message_id', $message->id)->whereNotNull('sent_at')->count();
			$hasDeliveriesPending = $totalDeliveries > $sentDeliveries;
			$campaignIsActive = $message->status_id == 1;
			$campaignCanBePaused = $campaignIsActive && ($totalDeliveries > 0 || $message->started_at);
		@endphp

		@if($campaignCanBePaused)
			<button class="btn btn-warning me-2" onclick="pauseCampaign({{ $message->id }})">
				<i class="ti ti-player-pause me-1"></i>Pausar
			</button>
		@else
			<button class="btn btn-success me-2 {{ !$canSend ? 'disabled' : '' }}"
					onclick="{{ $canSend ? 'startCampaign(' . $message->id . ')' : 'showAuthorizationError()' }}"
					{{ !$canSend ? 'disabled' : '' }}>
				<i class="ti ti-send me-1"></i>Enviar ahora
			</button>
		@endif

		<!-- Edit Button -->
		@can('message.edit')
		<a href="{{ route('message.edit', $message->id) }}" class="btn btn-primary waves-effect waves-light">
			<i class="ti ti-edit me-1"></i>Editar
		</a>
		@endcan

		<a href="{{ route('message.index') }}" class="btn btn-label-secondary">
			<i class="ti ti-arrow-left me-1"></i>Volver
		</a>
	</div>
</div>

<!-- Configuration Alerts (if any issues) -->
@if(isset($dnsStatus))
@php
	$isAuthorized = $dnsStatus['spf']['has_mailbaby'] && $dnsStatus['mailbaby_auth']['authorized'];
	$usingSystemSmtp = auth()->user()->currentTeam->isUsingSystemSmtp();
	$hasConfigIssues = $usingSystemSmtp && (!$dnsStatus['spf']['has_mailbaby'] || !$isAuthorized);
@endphp

@if($hasConfigIssues)
<div class="row mb-3">
	<div class="col-12">
		@if(!$dnsStatus['spf']['has_mailbaby'])
			<div class="alert alert-warning" role="alert">
				<i class="ti ti-alert-triangle me-2"></i>
				<strong>SPF Configuration Required:</strong>
				Add TXT record: <code>"v=spf1 include:spf.revisionalpha.com -all"</code> to domain <strong>{{ $dnsStatus['domain'] }}</strong>
			</div>
		@endif

		@if($usingSystemSmtp && !$isAuthorized)
			<div class="alert alert-danger" role="alert">
				<i class="ti ti-x-circle me-2"></i>
				<strong>Domain Not Authorized:</strong>
				Your domain <strong>{{ $dnsStatus['domain'] }}</strong> is not authorized to use system SMTP. Email sending is disabled.
			</div>
		@endif
	</div>
</div>
@endif
@endif

<div class="row">
	<!-- Left Column: Stats + General Info -->
	<div class="col-lg-4 col-md-5">
		<!-- Delivery Stats Component (Auto-updating) -->
		@livewire('delivery-stats', ['messageId' => $message->id])

		<!-- General Info -->
		<div class="card mb-4">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h5 class="mb-0">General Information</h5>
				<button class="btn btn-sm btn-outline-info {{ !$canSend ? 'disabled' : '' }}"
						onclick="{{ $canSend ? 'testSend(' . $message->id . ')' : 'showAuthorizationError()' }}"
						{{ !$canSend ? 'disabled' : '' }}>
					<i class="ti ti-send-2 me-1"></i>Test Send
				</button>
			</div>
			<div class="card-body">
				@if(empty($emailConfig['from_name']) || empty($emailConfig['from_address']))
					<div class="alert alert-warning mb-3" role="alert">
						<i class="ti ti-alert-triangle me-2"></i>
						<strong>Email sender not configured.</strong>
						<a href="{{ route('team-settings.edit', ['team' => auth()->user()->current_team_id, 'group' => 'email']) }}" class="alert-link">
							Configure it here
						</a>
					</div>
				@else
					<div class="mb-2"><strong>Sender:</strong> {{ $emailConfig['from_name'] }}</div>
					<div class="mb-2"><strong>Email:</strong> {{ $emailConfig['from_address'] }}</div>
				@endif
				<div class="mb-2"><strong>Category:</strong>
					@if($message->category)
						{{ $message->category->name }}
					@else
						All contacts
					@endif
				</div>
				<div class="mb-2"><strong>Contact Status:</strong>
					@if($message->contactStatus)
						{{ $message->contactStatus->name }}
					@else
						<span class="text-muted">All statuses</span>
					@endif
				</div>
			</div>
		</div>

		<!-- Email Plans Information -->
		<div class="card mb-4">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h5 class="card-title mb-0">Email Plan</h5>
				@php
					$team = auth()->user()->currentTeam;
					$currentPlan = $team->getEmailPlan();
					$remaining = $team->getRemainingEmails();
				@endphp

				<span class="badge bg-label-{{ $currentPlan->value === 'basic' ? 'primary' : ($currentPlan->value === 'foundation' ? 'info' : 'success') }}">
					{{ $currentPlan->getDisplayName() }}
				</span>
			</div>
			<div class="card-body">
				<!-- Monthly Usage -->
				<div class="mb-3">
					<div class="d-flex justify-content-between mb-2">
						<span class="text-muted">Monthly Usage</span>
						<span class="fw-semibold">{{ number_format($remaining['monthly_used']) }} / {{ number_format($remaining['monthly_limit']) }}</span>
					</div>
					<div class="progress" style="height: 8px;">
						@php
							$monthlyPercent = $remaining['monthly_limit'] > 0 ? ($remaining['monthly_used'] / $remaining['monthly_limit']) * 100 : 0;
							$monthlyColor = $monthlyPercent >= 100 ? 'danger' : ($monthlyPercent >= 80 ? 'warning' : 'success');
						@endphp
						<div class="progress-bar bg-{{ $monthlyColor }}" role="progressbar"
							 style="width: {{ min(100, $monthlyPercent) }}%"
							 aria-valuenow="{{ $monthlyPercent }}" aria-valuemin="0" aria-valuemax="100">
						</div>
					</div>
				</div>

				<!-- Daily Usage -->
				<div class="mb-3">
					<div class="d-flex justify-content-between mb-2">
						<span class="text-muted">Daily Usage</span>
						<span class="fw-semibold">
							{{ number_format($remaining['daily_used']) }} /
							{{ $remaining['daily_limit'] ? number_format($remaining['daily_limit']) : '∞' }}
						</span>
					</div>
					<div class="progress" style="height: 8px;">
						@if($remaining['daily_limit'])
							@php
								$dailyPercent = $remaining['daily_limit'] > 0 ? ($remaining['daily_used'] / $remaining['daily_limit']) * 100 : 0;
								$dailyColor = $dailyPercent >= 100 ? 'danger' : ($dailyPercent >= 80 ? 'warning' : 'success');
							@endphp
							<div class="progress-bar bg-{{ $dailyColor }}" role="progressbar"
								 style="width: {{ min(100, $dailyPercent) }}%"
								 aria-valuenow="{{ $dailyPercent }}" aria-valuemin="0" aria-valuemax="100">
							</div>
						@else
							<div class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
						@endif
					</div>
				</div>

				<!-- Contacts -->
				<div class="mb-3">
					<div class="d-flex justify-content-between mb-2">
						<span class="text-muted">Contacts</span>
						<span class="fw-semibold">{{ number_format($team->contacts()->count()) }} / {{ number_format($team->getContactLimit()) }}</span>
					</div>
					<div class="progress" style="height: 8px;">
						@php
							$contactsCount = $team->contacts()->count();
							$contactLimit = $team->getContactLimit();
							$contactsPercent = $contactLimit > 0 ? ($contactsCount / $contactLimit) * 100 : 0;
							$contactsColor = $contactsPercent >= 100 ? 'danger' : ($contactsPercent >= 80 ? 'warning' : 'success');
						@endphp
						<div class="progress-bar bg-{{ $contactsColor }}" role="progressbar"
							 style="width: {{ min(100, $contactsPercent) }}%"
							 aria-valuenow="{{ $contactsPercent }}" aria-valuemin="0" aria-valuemax="100">
						</div>
					</div>
				</div>

				<!-- Upgrade button if needed -->
				@php
					$isOverLimits = ($remaining['monthly_used'] >= $remaining['monthly_limit']) ||
									($remaining['daily_limit'] && $remaining['daily_used'] >= $remaining['daily_limit']);
				@endphp
				@if($isOverLimits)
					<div class="text-center mt-3">
						<a href="{{ route('billing.plans') }}" class="btn btn-sm btn-primary">
							<i class="ti ti-arrow-up me-1"></i>Upgrade Plan
						</a>
					</div>
				@endif
			</div>
		</div>
	</div>

	<!-- Right Column: Deliveries -->
	<div class="col-lg-8 col-md-7">
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h5 class="mb-0">Entregas</h5>
				<div class="d-flex align-items-center">
					<input type="text" id="searchDeliveries" class="form-control form-control-sm me-2" placeholder="Buscar..." style="width: 200px;">
					<i class="ti ti-search"></i>
				</div>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-hover" id="deliveriesTable">
						<thead>
							<tr>
								<th>CONTACTO</th>
								<th>ESTADO DE ENTREGA</th>
								<th>ESTADO</th>
								<th class="text-center">ACCIONES</th>
							</tr>
						</thead>
						<tbody>
							@forelse($deliveries as $delivery)
							<tr>
								<td>
									<div>
										<strong>{{ $delivery->contact->name ?? 'N/A' }}</strong><br>
										<small class="text-muted">{{ $delivery->contact->email ?? 'N/A' }}</small>
									</div>
								</td>
								<td>
									@if($delivery->delivered_at)
										<span class="text-success">
											<i class="ti ti-check me-1"></i>
											Entregado: {{ $delivery->delivered_at->format('M d, Y H:i') }}
										</span><br>
										<small class="text-muted">Enviado: {{ $delivery->sent_at ? $delivery->sent_at->format('M d, Y H:i') : 'N/A' }}</small>
									@elseif($delivery->sent_at)
										<span class="text-info">
											<i class="ti ti-clock me-1"></i>
											Enviado: {{ $delivery->sent_at->format('M d, Y H:i') }}
										</span>
									@elseif($delivery->failed_at)
										<span class="text-danger">
											<i class="ti ti-x me-1"></i>
											Failed: {{ $delivery->failed_at->format('M d, Y H:i') }}
										</span>
									@else
										<span class="text-muted">
											<i class="ti ti-clock me-1"></i>
											Pending
										</span>
									@endif
								</td>
								<td>
									@if($delivery->delivered_at)
										<span class="badge bg-success">Delivered</span>
									@elseif($delivery->sent_at)
										<span class="badge bg-info">Sent</span>
									@elseif($delivery->failed_at)
										<span class="badge bg-danger">Failed</span>
									@else
										<span class="badge bg-secondary">Pending</span>
									@endif
								</td>
								<td>
									<div class="d-flex justify-content-center align-items-center">
										@if($delivery->opened_at)
											<i class="ti ti-eye text-success me-2" title="Abierto"></i>
										@endif
										@if($delivery->clicked_at)
											<i class="ti ti-mouse text-primary me-2" title="Clickeado"></i>
										@endif
										<a href="javascript:;" class="text-primary" onclick="resendDelivery({{ $delivery->id }})" title="Reenviar">
											<i class="ti ti-send ti-sm"></i>
										</a>
									</div>
								</td>
							</tr>
							@empty
							<tr>
								<td colspan="4" class="text-center text-muted py-4">
									No deliveries found
								</td>
							</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
function previewMessage() {
	window.open('{{ route('message.preview', $message->id) }}', '_blank');
}

function pauseCampaign(messageId) {
	if (!confirm('Are you sure you want to pause this campaign?')) {
		return;
	}

	fetch(`/message/${messageId}/pause`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': '{{ csrf_token() }}'
		}
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			alert(data.message);
			location.reload();
		} else {
			alert('Error: ' + data.message);
		}
	})
	.catch(error => {
		console.error('Error:', error);
		alert('An error occurred while pausing the campaign');
	});
}

function startCampaign(messageId) {
	if (!confirm('Are you sure you want to start this campaign?')) {
		return;
	}

	fetch(`/message/${messageId}/start`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': '{{ csrf_token() }}'
		}
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			alert(data.message);
			location.reload();
		} else {
			alert('Error: ' + data.message);
		}
	})
	.catch(error => {
		console.error('Error:', error);
		alert('An error occurred while starting the campaign');
	});
}

function showAuthorizationError() {
	alert('Domain not authorized for sending emails. Please contact technical support to authorize email sending from your domain.');
}

function testSend(messageId) {
	if (!confirm('¿Enviar un correo de prueba a tu dirección ({{ auth()->user()->email }})?')) {
		return;
	}

	// Show loading indicator
	const originalButton = event.target.closest('button');
	const originalText = originalButton.innerHTML;
	originalButton.disabled = true;
	originalButton.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Enviando...';

	fetch(`/message/${messageId}/test`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': '{{ csrf_token() }}'
		}
	})
	.then(response => response.json())
	.then(data => {
		originalButton.disabled = false;
		originalButton.innerHTML = originalText;

		if (data.success) {
			alert('✅ Correo de prueba enviado exitosamente a ' + data.email);
		} else {
			alert('❌ Error: ' + data.message);
		}
	})
	.catch(error => {
		console.error('Error:', error);
		originalButton.disabled = false;
		originalButton.innerHTML = originalText;
		alert('❌ Ocurrió un error al enviar el correo de prueba');
	});
}

function resendDelivery(deliveryId) {
	if (!confirm('¿Estás seguro de que deseas reenviar este correo?')) {
		return;
	}

	fetch(`/message/delivery/${deliveryId}/resend`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': '{{ csrf_token() }}'
		}
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			alert('✅ ' + data.message);
			location.reload();
		} else {
			alert('❌ Error: ' + data.message);
		}
	})
	.catch(error => {
		console.error('Error:', error);
		alert('❌ Ocurrió un error al reenviar el correo');
	});
}

// Search functionality for deliveries table
document.addEventListener('DOMContentLoaded', function() {
	const searchInput = document.getElementById('searchDeliveries');
	if (searchInput) {
		searchInput.addEventListener('keyup', function() {
			const searchTerm = this.value.toLowerCase();
			const table = document.getElementById('deliveriesTable');
			const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

			for (let i = 0; i < rows.length; i++) {
				const row = rows[i];
				const contactCell = row.cells[0];
				
				if (contactCell) {
					const text = contactCell.textContent || contactCell.innerText;
					if (text.toLowerCase().indexOf(searchTerm) > -1) {
						row.style.display = '';
					} else {
						row.style.display = 'none';
					}
				}
			}
		});
	}
});
</script>
@endsection
