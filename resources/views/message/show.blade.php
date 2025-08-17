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

		<!-- Send/Pause Toggle Button - Only show if sender is configured -->
		@if(($emailConfig['from_name'] ?? '') !== 'Not configured' && ($emailConfig['from_address'] ?? '') !== 'Not configured' && !empty($emailConfig['from_name']) && !empty($emailConfig['from_address']))
			@if($message->status_id == 1 && ($stats_db->sent ?? 0) < ($stats_db->subscribers ?? 0))
				<button class="btn btn-warning me-2" onclick="pauseCampaign({{ $message->id }})">
					<i class="ti ti-player-pause me-1"></i>Pause
				</button>
			@else
				<button class="btn btn-success me-2" onclick="startCampaign({{ $message->id }})">
					<i class="ti ti-send me-1"></i>Send Now
				</button>
			@endif
		@else
			<!-- Show configuration message when sender is not configured -->
			<span class="text-muted fst-italic me-2">
				<i class="ti ti-alert-triangle me-1"></i>Configure sender to enable sending
			</span>
		@endif

		<a href="{{ route('message-list') }}" class="btn btn-label-secondary">
			<i class="ti ti-arrow-left me-1"></i>Back to list
		</a>
	</div>
</div>

<div class="row">
	<!-- Left Column: Stats + General Info -->
	<div class="col-lg-4 col-md-5">
		<!-- Delivery Stats Component (Auto-updating) -->
		@livewire('delivery-stats', ['messageId' => $message->id])

		<!-- General Info -->
		<div class="card mb-4">
			<div class="card-header d-flex justify-content-between align-items-center">
				<span>General Information</span>
				@if(($emailConfig['from_name'] ?? '') !== 'Not configured' && ($emailConfig['from_address'] ?? '') !== 'Not configured' && !empty($emailConfig['from_name']) && !empty($emailConfig['from_address']))
					<button class="btn btn-sm btn-outline-info" onclick="testSend({{ $message->id }})">
						<i class="ti ti-send-2 me-1"></i>Test Send
					</button>
				@endif
			</div>
			<div class="card-body">
				<div class="mb-2"><strong>Subject:</strong> {{ $message->name }}</div>
				<div class="mb-2"><strong>Sender:</strong>
					<span class="{{ ($emailConfig['from_name'] ?? '') === 'Not configured' ? 'text-danger' : 'text-success' }}">
						{{ $emailConfig['from_name'] ?? 'Not configured' }}
					</span>
				</div>
				<div class="mb-2"><strong>Sender Email:</strong>
					<span class="{{ ($emailConfig['from_address'] ?? '') === 'Not configured' ? 'text-danger' : 'text-success' }}">
						{{ $emailConfig['from_address'] ?? 'Not configured' }}
					</span>
				</div>
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
