@extends('layouts/layoutMaster')

@section('title', 'Message Detail')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3">
			<span class="text-muted fw-light">Mensajes/</span> {{ $message->name }}
		</h4>
		<p class="text-muted">Vista detallada del mensaje y sus estadísticas</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3">
		<!-- Preview Button -->
		<button class="btn btn-outline-primary me-2" onclick="previewMessage()">
			<i class="ti ti-eye me-1"></i>Vista previa
		</button>

		@php
			$isMailerMessage = (int) $message->type_id === 1;
			$visualEditorUrl = ($isMailerMessage && $message->template)
				? route('template.editor', $message->template->getHashedId())
				: route('message.edit', $message->id);
		@endphp
		<a href="{{ $visualEditorUrl }}" class="btn btn-primary waves-effect waves-light">
			@if ($isMailerMessage && $message->template)
				<i class="ti ti-external-link me-1"></i>{{ __('Abrir editor visual') }}
			@else
				<i class="ti ti-edit me-1"></i>{{ __('Editar') }}
			@endif
		</a>
		@if ($isMailerMessage && $message->template)
			<a href="{{ route('message.edit', $message->id) }}" class="btn btn-label-secondary waves-effect waves-light">
				<i class="ti ti-settings me-1"></i>{{ __('Configuración') }}
			</a>
		@endif

		<!-- Send/Pause Toggle Button - Only show if sender is configured -->
		@php
			$isAuthorized = isset($dnsStatus) && $dnsStatus['spf']['has_mailbaby'] && $dnsStatus['mailbaby_auth']['authorized'];
			$usingSystemSmtp = auth()->user()->currentTeam->isUsingSystemSmtp();
			$canSend = !$usingSystemSmtp || $isAuthorized;
		@endphp

		@php
			// Check if campaign is active and has deliveries pending or in progress
			$totalDeliveries = \App\Models\MessageDelivery::where('message_id', $message->id)->count();
			$deliveredCount = \App\Models\MessageDelivery::where('message_id', $message->id)->whereNotNull('delivered_at')->count();
			// Show "Send Now" button if there are deliveries not yet delivered (regardless of sent_at)
			$hasDeliveriesPending = $totalDeliveries > $deliveredCount;
			$campaignIsActive = $message->status_id == 1;
			$campaignCanBePaused = $campaignIsActive && ($totalDeliveries > 0 || $message->started_at);
		@endphp

		@if($campaignCanBePaused)
			<button class="btn btn-warning me-2" onclick="pauseCampaign({{ $message->id }})">
				<i class="ti ti-player-pause me-1"></i>Pausar
			</button>
		@if($hasDeliveriesPending)
			<button class="btn btn-info me-2" onclick="sendPendingNow({{ $message->id }})">
				<i class="ti ti-refresh me-1"></i>Recalcular envíos
			</button>
		@endif
		@else
			@if (! $message->campaigns_exists)
				<button class="btn btn-success me-2 {{ !$canSend ? 'disabled' : '' }}"
						onclick="{{ $canSend ? 'startCampaign(' . $message->id . ')' : 'showAuthorizationError()' }}"
						{{ !$canSend ? 'disabled' : '' }}>
					<i class="ti ti-send me-1"></i>Enviar ahora
				</button>
			@endif
		@endif

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

@if (filled($emailTemplatePreviewHtml ?? null) && filled($emailTemplateGrapesUrl ?? null) && $message->template)
	<div class="row mb-1">
		<div class="col-12">
			@include('message.partials.email-template-content-preview', [
				'previewHtml' => $emailTemplatePreviewHtml,
				'grapesEditorUrl' => $emailTemplateGrapesUrl,
				'templateLabel' => $message->template->name,
				'messageId' => $message->id,
			])
		</div>
	</div>
@endif

<div class="row">
	<!-- Left Column: Stats + General Info -->
	<div class="col-lg-4 col-md-5">
		<!-- Delivery Stats Component (Auto-updating) -->
		@livewire('delivery-stats', ['messageId' => $message->id])

		<!-- General Info -->
		<div class="card mb-4">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h5 class="mb-0">{{ __('General Information') }}</h5>
				<button class="btn btn-sm btn-outline-info {{ !$canSend ? 'disabled' : '' }}"
						onclick="{{ $canSend ? 'testSend(' . $message->id . ')' : 'showAuthorizationError()' }}"
						{{ !$canSend ? 'disabled' : '' }}>
					<i class="ti ti-send-2 me-1"></i>{{ __('Test Send') }}
				</button>
			</div>
			<div class="card-body">
				@if(empty($emailConfig['from_name']) || empty($emailConfig['from_address']))
					<div class="alert alert-warning mb-3" role="alert">
						<i class="ti ti-alert-triangle me-2"></i>
						<strong>{{ __('Email sender not configured.') }}</strong>
						<a href="{{ route('team-settings.edit', ['team' => auth()->user()->current_team_id, 'group' => 'email']) }}" class="alert-link">
							{{ __('Configure it here') }}
						</a>
					</div>
				@else
					<div class="mb-2"><strong>{{ __('Sender') }}:</strong> {{ $emailConfig['from_name'] }}</div>
					<div class="mb-2"><strong>{{ __('Email') }}:</strong> {{ $emailConfig['from_address'] }}</div>
				@endif
				<div class="mb-2">
					<strong>{{ __('Category') }}:</strong>
					@if($message->category)
						<span class="badge bg-label-primary">{{ $message->category->name }}</span>
					@else
						<span class="badge bg-label-secondary">{{ __('All contacts') }}</span>
					@endif
				</div>
				<div class="mb-2">
					<strong>{{ __('Contact Status') }}:</strong>
					@if($message->contactStatus)
						<span class="badge bg-label-success">{{ $message->contactStatus->name }}</span>
					@else
						<span class="badge bg-label-secondary">{{ __('All statuses') }}</span>
					@endif
				</div>
			</div>
		</div>

		<!-- Email Plans Information (Livewire Component) -->
		@livewire('email-plan-info')
	</div>

	<!-- Right Column: Deliveries -->
	<div class="col-lg-8 col-md-7">
		{{-- Deliveries component with pagination --}}
		@livewire('message-deliveries', ['messageId' => $message->id])
	</div>
</div>

<style>
	/* Remove border from last delivery row */
	.delivery-tbody tr:last-child td {
		border-bottom: none !important;
	}
</style>

<script>
function previewMessage() {
	window.open('{{ route('message.preview', $message->id) }}', '_blank');
}

function pauseCampaign(messageId) {
	Swal.fire({
		title: '¿Pausar campaña?',
		text: '¿Estás seguro de que deseas pausar esta campaña?',
		icon: 'warning',
		showCancelButton: true,
		confirmButtonText: 'Sí, pausar',
		cancelButtonText: 'Cancelar',
		customClass: {
			confirmButton: 'btn btn-primary me-3 waves-effect waves-light',
			cancelButton: 'btn btn-label-secondary waves-effect waves-light'
		},
		buttonsStyling: false
	}).then((result) => {
		if (result.isConfirmed) {
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
					Swal.fire({
						title: '¡Pausada!',
						text: data.message,
						icon: 'success',
						customClass: {
							confirmButton: 'btn btn-success waves-effect waves-light'
						},
						buttonsStyling: false
					}).then(() => {
			location.reload();
					});
		} else {
					Swal.fire({
						title: 'Error',
						text: data.message,
						icon: 'error',
						customClass: {
							confirmButton: 'btn btn-danger waves-effect waves-light'
						},
						buttonsStyling: false
					});
		}
	})
	.catch(error => {
		console.error('Error:', error);
				Swal.fire({
					title: 'Error',
					text: 'Ha ocurrido un error al pausar la campaña',
					icon: 'error',
					customClass: {
						confirmButton: 'btn btn-danger waves-effect waves-light'
					},
					buttonsStyling: false
				});
			});
		}
	});
}

function sendPendingNow(messageId) {
	Swal.fire({
		title: '¿Recalcular envíos?',
		text: 'Esto reprogramará todos los correos pendientes y encolará los primeros 100 para envío inmediato',
		icon: 'question',
		showCancelButton: true,
		confirmButtonText: 'Sí, recalcular',
		cancelButtonText: 'Cancelar',
		customClass: {
			confirmButton: 'btn btn-primary me-3 waves-effect waves-light',
			cancelButton: 'btn btn-label-secondary waves-effect waves-light'
		},
		buttonsStyling: false,
		showLoaderOnConfirm: true,
		preConfirm: () => {
			return fetch(`/message/${messageId}/send-pending-now`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': '{{ csrf_token() }}'
				}
			})
			.then(response => {
				return response.json().then(data => {
					if (!response.ok) {
						// Si la respuesta no es OK, lanzar error con el mensaje del servidor
						throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
					}
					return data;
				});
			})
			.catch(error => {
				console.error('Error completo:', error);
				Swal.showValidationMessage(`${error.message || error}`);
			});
		},
		allowOutsideClick: () => !Swal.isLoading()
	}).then((result) => {
		if (result.isConfirmed && result.value) {
			if (result.value.success) {
				let message = result.value.message;
				if (result.value.remaining > 0) {
					message += `\n\nLos restantes ${result.value.remaining} serán enviados automáticamente por el programador cada minuto.`;
				}
				Swal.fire({
					title: '¡Proceso completado!',
					text: message,
					icon: 'success',
					customClass: {
						confirmButton: 'btn btn-success waves-effect waves-light'
					},
					buttonsStyling: false
				}).then(() => {
					location.reload();
				});
			} else {
				Swal.fire({
					title: 'Error',
					text: result.value.message,
					icon: 'error',
					customClass: {
						confirmButton: 'btn btn-danger waves-effect waves-light'
					},
					buttonsStyling: false
				});
			}
		}
	});
}

function startCampaign(messageId) {
	Swal.fire({
		title: '¿Iniciar campaña?',
		text: '¿Estás seguro de que deseas iniciar esta campaña?',
		icon: 'question',
		showCancelButton: true,
		confirmButtonText: 'Sí, iniciar',
		cancelButtonText: 'Cancelar',
		customClass: {
			confirmButton: 'btn btn-primary me-3 waves-effect waves-light',
			cancelButton: 'btn btn-label-secondary waves-effect waves-light'
		},
		buttonsStyling: false
	}).then((result) => {
		if (result.isConfirmed) {
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
					Swal.fire({
						title: '¡Iniciada!',
						text: data.message,
						icon: 'success',
						customClass: {
							confirmButton: 'btn btn-success waves-effect waves-light'
						},
						buttonsStyling: false
					}).then(() => {
			location.reload();
					});
		} else {
					Swal.fire({
						title: 'Error',
						text: data.message,
						icon: 'error',
						customClass: {
							confirmButton: 'btn btn-danger waves-effect waves-light'
						},
						buttonsStyling: false
					});
		}
	})
	.catch(error => {
		console.error('Error:', error);
				Swal.fire({
					title: 'Error',
					text: 'Ha ocurrido un error al iniciar la campaña',
					icon: 'error',
					customClass: {
						confirmButton: 'btn btn-danger waves-effect waves-light'
					},
					buttonsStyling: false
				});
			});
		}
	});
}

function showAuthorizationError() {
	Swal.fire({
		title: 'Dominio no autorizado',
		text: 'Tu dominio no está autorizado para enviar correos. Por favor, contacta con el soporte técnico para autorizar el envío de correos desde tu dominio.',
		icon: 'warning',
		confirmButtonText: 'Entendido',
		customClass: {
			confirmButton: 'btn btn-primary waves-effect waves-light'
		},
		buttonsStyling: false
	});
}

function testSend(messageId) {
	Swal.fire({
		title: 'Enviar correo de prueba',
		text: '¿Enviar un correo de prueba a {{ auth()->user()->email }}?',
		icon: 'question',
		showCancelButton: true,
		confirmButtonText: 'Sí, enviar',
		cancelButtonText: 'Cancelar',
		customClass: {
			confirmButton: 'btn btn-primary me-3 waves-effect waves-light',
			cancelButton: 'btn btn-label-secondary waves-effect waves-light'
		},
		buttonsStyling: false
	}).then((result) => {
		if (result.isConfirmed) {
			Swal.fire({
				title: 'Enviando...',
				text: 'Por favor espera',
				icon: 'info',
				allowOutsideClick: false,
				allowEscapeKey: false,
				showConfirmButton: false,
				willOpen: () => {
					Swal.showLoading();
				}
			});

	fetch(`/message/${messageId}/test`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': '{{ csrf_token() }}'
		}
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
					Swal.fire({
						title: '¡Enviado!',
						text: 'Correo de prueba enviado exitosamente a ' + data.email,
						icon: 'success',
						customClass: {
							confirmButton: 'btn btn-success waves-effect waves-light'
						},
						buttonsStyling: false
					});
		} else {
					Swal.fire({
						title: 'Error',
						text: data.message,
						icon: 'error',
						customClass: {
							confirmButton: 'btn btn-danger waves-effect waves-light'
						},
						buttonsStyling: false
					});
		}
	})
	.catch(error => {
		console.error('Error:', error);
				Swal.fire({
					title: 'Error',
					text: 'Ocurrió un error al enviar el correo de prueba',
					icon: 'error',
					customClass: {
						confirmButton: 'btn btn-danger waves-effect waves-light'
					},
					buttonsStyling: false
				});
			});
		}
	});
}

function resendDelivery(deliveryId) {
	Swal.fire({
		title: '¿Reenviar correo?',
		text: '¿Estás seguro de que deseas reenviar este correo?',
		icon: 'question',
		showCancelButton: true,
		confirmButtonText: 'Sí, reenviar',
		cancelButtonText: 'Cancelar',
		customClass: {
			confirmButton: 'btn btn-primary me-3 waves-effect waves-light',
			cancelButton: 'btn btn-label-secondary waves-effect waves-light'
		},
		buttonsStyling: false,
		showLoaderOnConfirm: true,
		preConfirm: () => {
			return fetch(`/message/delivery/${deliveryId}/resend`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': '{{ csrf_token() }}'
				}
			})
			.then(response => {
				if (!response.ok) {
					throw new Error(response.statusText);
				}
				return response.json();
			})
			.catch(error => {
				Swal.showValidationMessage(`Error: ${error}`);
			});
		},
		allowOutsideClick: () => !Swal.isLoading()
	}).then((result) => {
		if (result.isConfirmed && result.value) {
			if (result.value.success) {
				Swal.fire({
					title: '¡Reenviado!',
					text: result.value.message,
					icon: 'success',
					customClass: {
						confirmButton: 'btn btn-success waves-effect waves-light'
					},
					buttonsStyling: false
				}).then(() => {
					location.reload();
				});
			} else {
				Swal.fire({
					title: 'Error',
					text: result.value.message,
					icon: 'error',
					customClass: {
						confirmButton: 'btn btn-danger waves-effect waves-light'
					},
					buttonsStyling: false
				});
			}
		}
	});
}

// Filter deliveries by status
let currentFilter = 'all';

function filterDeliveries(filterType) {
	const table = document.getElementById('deliveriesTable');
	const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

	// Toggle filter: if clicking the same filter, show all
	if (currentFilter === filterType) {
		currentFilter = 'all';
		filterType = 'all';
	} else {
		currentFilter = filterType;
	}

	for (let i = 0; i < rows.length; i++) {
		const row = rows[i];
		const statusCell = row.cells[1]; // Estado de Entrega column

		if (!statusCell) continue;

		const statusText = statusCell.textContent || statusCell.innerText;
		let shouldShow = false;

		switch(filterType) {
			case 'all':
				shouldShow = true;
				break;
			case 'sent':
				shouldShow = statusText.includes('Enviado:') && !statusText.includes('Entregado:');
				break;
			case 'delivered':
				shouldShow = statusText.includes('Entregado:');
				break;
			case 'opened':
				// Check if has eye icon in actions column
				shouldShow = row.cells[2] && row.cells[2].innerHTML.includes('ti-eye');
				break;
			case 'clicked':
				// Check if has mouse icon in actions column
				shouldShow = row.cells[2] && row.cells[2].innerHTML.includes('ti-mouse');
				break;
			case 'failed':
				shouldShow = statusText.includes('Failed:');
				break;
		}

		row.style.display = shouldShow ? '' : 'none';
	}

	// Update search to work with filtered rows
	const searchInput = document.getElementById('searchDeliveries');
	if (searchInput && searchInput.value) {
		searchInput.dispatchEvent(new Event('keyup'));
	}
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
