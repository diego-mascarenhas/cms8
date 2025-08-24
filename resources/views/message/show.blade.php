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
		<button class="btn btn-primary me-2" onclick="previewMessage()">
			<i class="ti ti-eye me-1"></i>Preview
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
				<i class="ti ti-player-pause me-1"></i>Pause
			</button>
		@else
			<button class="btn btn-success me-2 {{ !$canSend ? 'disabled' : '' }}"
					onclick="{{ $canSend ? 'startCampaign(' . $message->id . ')' : 'showAuthorizationError()' }}"
					{{ !$canSend ? 'disabled' : '' }}>
				<i class="ti ti-send me-1"></i>Send Now
			</button>
		@endif

		<a href="{{ route('message-list') }}" class="btn btn-label-secondary">
			<i class="ti ti-arrow-left me-1"></i>Back to list
		</a>
	</div>
</div>

<!-- Configuration Alerts (if any issues) -->
@if($dnsStatus)
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
				<div class="mb-2"><strong>Sender:</strong> {{ $emailConfig['from_name'] }}</div>
				<div class="mb-2"><strong>Email:</strong> {{ $emailConfig['from_address'] }}</div>
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
	</div>

	<!-- Right Column: Deliveries Table -->
	<div class="col-lg-8 col-md-7">
		@livewire('message-deliveries', ['messageId' => $message->id])
	</div>
</div>

<div class="row">
	<div class="col-md-12">
		<div class="card mb-4">
			<div class="card-header">Lead Conversion Links</div>
			<div class="card-body table-responsive">
				@if($links->count() > 0)
					<table class="table table-sm">
						<thead>
							<tr>
								<th>Link</th>
								<th class="text-center">Unique Clicks</th>
								<th class="text-center">Total Clicks</th>
								<th>First Click</th>
								<th>Last Click</th>
								<th class="text-center">Actions</th>
							</tr>
						</thead>
						<tbody>
							@foreach($links as $link)
								<tr>
									<td>
										<span class="text-muted"
											  data-bs-toggle="tooltip"
											  data-bs-placement="top"
											  data-bs-original-title="{{ $link->link }}">
											{{ Str::limit($link->link, 60) }}
										</span>
									</td>
									<td class="text-center">
										<span class="badge bg-info">{{ $link->unique_clicks }}</span>
									</td>
									<td class="text-center">
										<span class="badge bg-primary">{{ $link->total_clicks }}</span>
									</td>
									<td>
										<small class="text-muted">
											{{ $link->first_click ? \Carbon\Carbon::parse($link->first_click)->format('M j, Y H:i') : 'N/A' }}
										</small>
									</td>
									<td>
										<small class="text-muted">
											{{ $link->last_click ? \Carbon\Carbon::parse($link->last_click)->format('M j, Y H:i') : 'Never' }}
										</small>
									</td>
																		<td class="text-center">
										<div class="d-flex justify-content-center align-items-center gap-2">
											<a href="{{ $link->link }}"
											   target="_blank"
											   data-bs-toggle="tooltip"
											   data-bs-placement="top"
											   data-bs-original-title="Open link: {{ $link->link }}"
											   class="text-body">
												<i class="ti ti-external-link ti-sm"></i>
											</a>
											<a href="javascript:;"
											   onclick="showLinkDetails('{{ base64_encode($link->link) }}', '{{ addslashes($link->link) }}')"
											   data-bs-toggle="tooltip"
											   data-bs-placement="top"
											   data-bs-original-title="View contacts who clicked"
											   class="text-body">
												<i class="ti ti-users ti-sm"></i>
											</a>
											<a href="javascript:;"
											   onclick="copyToClipboard('{{ $link->link }}')"
											   data-bs-toggle="tooltip"
											   data-bs-placement="top"
											   data-bs-original-title="Copy link"
											   class="text-body">
												<i class="ti ti-copy ti-sm"></i>
											</a>
										</div>
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				@else
					<div class="text-center py-4">
						<div class="mb-3">
							<i class="ti ti-link-off ti-lg text-muted"></i>
						</div>
						<h6 class="text-muted">No hay enlaces de conversión</h6>
						<p class="text-muted small">Los enlaces de conversión aparecerán aquí cuando los contactos interactúen con los emails enviados.</p>
					</div>
				@endif
			</div>
		</div>
	</div>
</div>

<!-- Modal for Link Contact Details -->
<div class="modal fade" id="linkDetailsModal" tabindex="-1" aria-labelledby="linkDetailsModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="linkDetailsModalLabel">
					<i class="ti ti-users me-2"></i>Contacts who clicked this link
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<strong>Link:</strong>
					<a href="#" id="modalLinkUrl" target="_blank" class="text-primary">
						<span id="modalLinkText"></span>
						<i class="ti ti-external-link ti-xs ms-1"></i>
					</a>
				</div>
				<div id="linkDetailsContent">
					<div class="text-center py-4">
						<div class="spinner-border text-primary" role="status">
							<span class="visually-hidden">Loading...</span>
						</div>
						<p class="mt-2 text-muted">Loading contact details...</p>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

@endsection

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-script')
<style>
/* Fix SweetAlert2 z-index issues */
.swal2-container {
    z-index: 999999 !important;
}
.swal2-popup {
    z-index: 999999 !important;
}
</style>
<script>
function previewMessage() {
    // Open preview in new window/tab
    const previewUrl = `{{ route('message.preview', $message->id ?? 0) }}`;
    window.open(previewUrl, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
}

// Copy to clipboard function
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success toast or notification
        if (typeof window.Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Copied!',
                text: 'Link copied to clipboard',
                timer: 1500,
                showConfirmButton: false
            });
        }
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
    });
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Show link details modal
function showLinkDetails(encodedLink, linkUrl) {
    // Set the link in the modal header
    document.getElementById('modalLinkUrl').href = linkUrl;
    document.getElementById('modalLinkText').textContent = linkUrl;

    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('linkDetailsModal'));
    modal.show();

    // Reset content to loading state
    document.getElementById('linkDetailsContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Loading contact details...</p>
        </div>
    `;

    // Fetch link details
    fetch(`/message/{{ $message->id }}/link-details/${encodedLink}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayLinkDetails(data.contacts, data.totalClicks, data.uniqueClicks);
            } else {
                displayError(data.message || 'Error loading contact details');
            }
        })
        .catch(error => {
            console.error('Error fetching link details:', error);
            displayError('Error loading contact details');
        });
}

function displayLinkDetails(contacts, totalClicks, uniqueClicks) {
    let html = `
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Contact Details</h6>
                <div>
                    <span class="badge bg-info me-2">${uniqueClicks} unique</span>
                    <span class="badge bg-primary">${totalClicks} total</span>
                </div>
            </div>
        </div>
    `;

    if (contacts && contacts.length > 0) {
        html += `
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Contact</th>
                            <th class="text-center">Clicks</th>
                            <th>First Click</th>
                            <th>Last Click</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        contacts.forEach(contact => {
            // Use different colors: info for single click, primary for multiple clicks
            const badgeClass = contact.click_count === 1 ? 'bg-info' : 'bg-primary';

            html += `
                <tr>
                    <td>
                        <div class="d-flex flex-column">
                            <h6 class="mb-0">${contact.name}</h6>
                            <small class="text-muted">${contact.email}</small>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="badge ${badgeClass}">${contact.click_count}</span>
                    </td>
                    <td>
                        <small class="text-muted">${contact.first_click}</small>
                    </td>
                    <td>
                        <small class="text-muted">${contact.last_click}</small>
                    </td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;
    } else {
        html += `
            <div class="text-center py-4">
                <i class="ti ti-users-off ti-lg text-muted"></i>
                <h6 class="text-muted mt-2">No clicks recorded</h6>
                <p class="text-muted small">This link hasn't been clicked by any contacts yet.</p>
            </div>
        `;
    }

    document.getElementById('linkDetailsContent').innerHTML = html;
}

function displayError(message) {
    document.getElementById('linkDetailsContent').innerHTML = `
        <div class="text-center py-4">
            <i class="ti ti-alert-circle ti-lg text-danger"></i>
            <h6 class="text-danger mt-2">Error</h6>
            <p class="text-muted small">${message}</p>
        </div>
    `;
}

function resendDelivery(deliveryId, element) {
    Swal.fire({
        title: '¿Reenviar este email?',
        text: 'Se creará una nueva entrega y se enviará inmediatamente',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, reenviar',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-info me-3 waves-effect waves-light',
            cancelButton: 'btn btn-label-secondary waves-effect waves-light'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            const originalHtml = element.innerHTML;
            element.innerHTML = '<i class="ti ti-loader ti-sm"></i>';
            element.style.pointerEvents = 'none';

            // Send AJAX request to resend delivery
            fetch(`/delivery/${deliveryId}/resend`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                // Restore original state
                element.innerHTML = originalHtml;
                element.style.pointerEvents = 'auto';

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Email reenviado!',
                        text: data.message,
                        customClass: {
                            confirmButton: 'btn btn-success waves-effect waves-light'
                        },
                        buttonsStyling: false
                    });

                    // Refresh the deliveries table (trigger Livewire polling)
                    if (typeof Livewire !== 'undefined') {
                        Livewire.dispatch('loadDeliveries');
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Ha ocurrido un error al reenviar el email',
                        customClass: {
                            confirmButton: 'btn btn-danger waves-effect waves-light'
                        },
                        buttonsStyling: false
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Restore original state
                element.innerHTML = originalHtml;
                element.style.pointerEvents = 'auto';

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Ha ocurrido un error al procesar la solicitud',
                    customClass: {
                        confirmButton: 'btn btn-danger waves-effect waves-light'
                    },
                    buttonsStyling: false
                });
            });
        }
    });
}

function startCampaign(messageId) {
    Swal.fire({
        title: '🚀 Iniciar Campaña',
        text: '¿Estás seguro de que quieres iniciar el envío de esta campaña?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, iniciar campaña',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-success me-3 waves-effect waves-light',
            cancelButton: 'btn btn-label-secondary waves-effect waves-light'
        },
        buttonsStyling: false
    }).then(function (result) {
        if (result.isConfirmed) {
            // Show loading in the current modal
            Swal.update({
                title: 'Iniciando campaña...',
                text: 'Por favor espera mientras se procesa la solicitud',
                icon: 'info',
                showConfirmButton: false,
                showCancelButton: false,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(`/message/${messageId}/start`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                // Check if response is actually JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Response is not JSON');
                }

                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);

                if (data.success) {
                    Swal.fire({
                        title: '¡Éxito!',
                        text: data.message || 'La campaña ha sido iniciada exitosamente',
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
                        text: data.message || 'Error al iniciar la campaña',
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-danger waves-effect waves-light'
                        },
                        buttonsStyling: false
                    });
                }
            })
                        .catch(error => {
                console.error('Error details:', error);
                console.error('Error message:', error.message);

                let errorMessage = 'Error desconocido';
                let showRefreshOption = false;

                if (error.message.includes('HTTP error! status: 419')) {
                    errorMessage = 'Tu sesión ha expirado. Por favor, recarga la página para continuar.';
                    showRefreshOption = true;
                } else if (error.message.includes('HTTP error! status:')) {
                    errorMessage = `Error del servidor: ${error.message}`;
                } else if (error.message.includes('Response is not JSON')) {
                    errorMessage = 'El servidor no devolvió una respuesta válida';
                } else if (error.message.includes('Failed to fetch')) {
                    errorMessage = 'No se pudo conectar con el servidor';
                } else {
                    errorMessage = error.message;
                }

                if (showRefreshOption) {
                    Swal.fire({
                        title: 'Sesión Expirada',
                        text: errorMessage,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Recargar Página',
                        cancelButtonText: 'Cancelar',
                        customClass: {
                            confirmButton: 'btn btn-warning waves-effect waves-light',
                            cancelButton: 'btn btn-secondary waves-effect waves-light'
                        },
                        buttonsStyling: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            location.reload();
                        }
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: errorMessage,
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-danger waves-effect waves-light'
                        },
                        buttonsStyling: false
                    });
                }
            });
        }
    });
}

function pauseCampaign(messageId) {
    Swal.fire({
        title: '⏸️ Pausar Campaña',
        text: '¿Estás seguro de que quieres pausar esta campaña?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, pausar campaña',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-warning me-3 waves-effect waves-light',
            cancelButton: 'btn btn-label-secondary waves-effect waves-light'
        },
        buttonsStyling: false
    }).then(function (result) {
        if (result.isConfirmed) {
            // Show loading in the current modal
            Swal.update({
                title: 'Pausando campaña...',
                text: 'Por favor espera mientras se procesa la solicitud',
                icon: 'info',
                showConfirmButton: false,
                showCancelButton: false,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(`/message/${messageId}/pause`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => {
                console.log('Pause - Response status:', response.status);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Response is not JSON');
                }

                return response.json();
            })
            .then(data => {
                console.log('Pause - Response data:', data);

                if (data.success) {
                    Swal.fire({
                        title: '¡Éxito!',
                        text: data.message || 'La campaña ha sido pausada exitosamente',
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
                        text: data.message || 'Error al pausar la campaña',
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-danger waves-effect waves-light'
                        },
                        buttonsStyling: false
                    });
                }
            })
                        .catch(error => {
                console.error('Pause - Error details:', error);

                let errorMessage = 'Error desconocido';
                let showRefreshOption = false;

                if (error.message.includes('HTTP error! status: 419')) {
                    errorMessage = 'Tu sesión ha expirado. Por favor, recarga la página para continuar.';
                    showRefreshOption = true;
                } else if (error.message.includes('HTTP error! status:')) {
                    errorMessage = `Error del servidor: ${error.message}`;
                } else if (error.message.includes('Response is not JSON')) {
                    errorMessage = 'El servidor no devolvió una respuesta válida';
                } else if (error.message.includes('Failed to fetch')) {
                    errorMessage = 'No se pudo conectar con el servidor';
                } else {
                    errorMessage = error.message;
                }

                if (showRefreshOption) {
                    Swal.fire({
                        title: 'Sesión Expirada',
                        text: errorMessage,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Recargar Página',
                        cancelButtonText: 'Cancelar',
                        customClass: {
                            confirmButton: 'btn btn-warning waves-effect waves-light',
                            cancelButton: 'btn btn-secondary waves-effect waves-light'
                        },
                        buttonsStyling: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            location.reload();
                        }
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: errorMessage,
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-danger waves-effect waves-light'
                        },
                        buttonsStyling: false
                    });
                }
            });
        }
    });
}

function showAuthorizationError() {
    Swal.fire({
        title: '🚫 Authorization Required',
        text: 'Your domain needs to be properly configured to send emails using system SMTP. Please configure your SPF record or use your own SMTP settings.',
        icon: 'warning',
        confirmButtonText: 'OK',
        customClass: {
            confirmButton: 'btn btn-warning waves-effect waves-light'
        },
        buttonsStyling: false
    });
}

function testSend(messageId) {
    Swal.fire({
        title: '🧪 Test Send',
        text: 'This will send a test email to your current email address',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Send Test Email',
        cancelButtonText: 'Cancel',
        customClass: {
            confirmButton: 'btn btn-info me-3 waves-effect waves-light',
            cancelButton: 'btn btn-label-secondary waves-effect waves-light'
        },
        buttonsStyling: false
    }).then(function (result) {
        if (result.isConfirmed) {
            // Show loading in the current modal
            Swal.update({
                title: 'Sending test email...',
                text: 'Please wait while we process your request',
                icon: 'info',
                showConfirmButton: false,
                showCancelButton: false,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(`/message/${messageId}/test`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => {
                console.log('Test - Response status:', response.status);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Response is not JSON');
                }

                return response.json();
            })
            .then(data => {
                console.log('Test - Response data:', data);

                if (data.success) {
                    Swal.fire({
                        title: '✅ Test Email Sent!',
                        text: `Test email sent successfully to ${data.email}`,
                        icon: 'success',
                        customClass: {
                            confirmButton: 'btn btn-success waves-effect waves-light'
                        },
                        buttonsStyling: false
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Error sending test email',
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-danger waves-effect waves-light'
                        },
                        buttonsStyling: false
                    });
                }
            })
                        .catch(error => {
                console.error('Test - Error details:', error);

                let errorMessage = 'Error desconocido';
                let showRefreshOption = false;

                if (error.message.includes('HTTP error! status: 419')) {
                    errorMessage = 'Tu sesión ha expirado. Por favor, recarga la página para continuar.';
                    showRefreshOption = true;
                } else if (error.message.includes('HTTP error! status:')) {
                    errorMessage = `Error del servidor: ${error.message}`;
                } else if (error.message.includes('Response is not JSON')) {
                    errorMessage = 'El servidor no devolvió una respuesta válida';
                } else if (error.message.includes('Failed to fetch')) {
                    errorMessage = 'No se pudo conectar con el servidor';
                } else {
                    errorMessage = error.message;
                }

                if (showRefreshOption) {
                    Swal.fire({
                        title: 'Sesión Expirada',
                        text: errorMessage,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Recargar Página',
                        cancelButtonText: 'Cancelar',
                        customClass: {
                            confirmButton: 'btn btn-warning waves-effect waves-light',
                            cancelButton: 'btn btn-secondary waves-effect waves-light'
                        },
                        buttonsStyling: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            location.reload();
                        }
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: errorMessage,
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-danger waves-effect waves-light'
                        },
                        buttonsStyling: false
                    });
                }
            });
        }
    });
}
</script>
@endsection
