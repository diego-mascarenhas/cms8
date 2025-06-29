@extends('layouts/layoutMaster')

@section('title', 'Notificaciones')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-script')
<script>
function sendNotification(id) {
    Swal.fire({
        title: '¿Enviar notificación?',
        text: 'La notificación será enviada por email al colaborador.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, enviar',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-primary me-2',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/notification/${id}/send`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: '¡Enviado!',
                        text: 'La notificación ha sido enviada correctamente.',
                        icon: 'success',
                        customClass: {
                            confirmButton: 'btn btn-success'
                        },
                        buttonsStyling: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Error al enviar la notificación.',
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        },
                        buttonsStyling: false
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error',
                    text: 'Error de conexión.',
                    icon: 'error',
                    customClass: {
                        confirmButton: 'btn btn-danger'
                    },
                    buttonsStyling: false
                });
            });
        }
    });
}

function resendNotification(id) {
    Swal.fire({
        title: '¿Reenviar notificación?',
        text: 'La notificación será enviada nuevamente por email.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, reenviar',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-primary me-2',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/notification/${id}/resend`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: '¡Reenviado!',
                        text: 'La notificación ha sido reenviada correctamente.',
                        icon: 'success',
                        customClass: {
                            confirmButton: 'btn btn-success'
                        },
                        buttonsStyling: false
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Error al reenviar la notificación.',
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        },
                        buttonsStyling: false
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error',
                    text: 'Error de conexión.',
                    icon: 'error',
                    customClass: {
                        confirmButton: 'btn btn-danger'
                    },
                    buttonsStyling: false
                });
            });
        }
    });
}

function deleteNotification(id) {
    Swal.fire({
        title: '¿Eliminar notificación?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-danger me-2',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/notification/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: '¡Eliminado!',
                        text: 'La notificación ha sido eliminada.',
                        icon: 'success',
                        customClass: {
                            confirmButton: 'btn btn-success'
                        },
                        buttonsStyling: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Error al eliminar la notificación.',
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        },
                        buttonsStyling: false
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error',
                    text: 'Error de conexión.',
                    icon: 'error',
                    customClass: {
                        confirmButton: 'btn btn-danger'
                    },
                    buttonsStyling: false
                });
            });
        }
    });
}
</script>
@endsection

@section('content')
<div class="row">
    <!-- Collaborator Sidebar -->
    @include('collaborator.partials.sidebar')
    <!--/ Collaborator Sidebar -->

    <!-- Notificaciones Content -->
    <div class="col-xl-8 col-lg-7 col-md-7">
        <!-- Tabs -->
        @include('collaborator.partials.tabs')
        
        <!-- Notificaciones -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">Notificaciones</h5>
                    @can('notification.create')
                    <a href="{{ route('notification.create') }}?contact_id={{ $collaborator->id }}" class="btn btn-primary btn-sm">
                        <i class="ti ti-plus me-1"></i>Enviar notificación
                    </a>
                    @endcan
                </div>
                
                @if($notifications->count() > 0)
                    @foreach($notifications as $notification)
                    <!-- Notification Item -->
                    <div class="d-flex gap-3 align-items-start mb-4 pb-3 {{ !$loop->last ? 'border-bottom' : '' }} position-relative">
                        @if(!$notification->is_sent)
                            <div class="position-absolute end-0 top-0">
                                <span class="bg-warning rounded-circle d-inline-block" style="width: 8px; height: 8px;" title="Pendiente de envío"></span>
                            </div>
                        @elseif($notification->is_sent && !$notification->is_read)
                            <div class="position-absolute end-0 top-0">
                                <span class="bg-primary rounded-circle d-inline-block" style="width: 8px; height: 8px;" title="No leído"></span>
                            </div>
                        @endif
                        
                        <div class="avatar avatar-md bg-light-{{ $notification->getAvatarColor() }} rounded-circle d-flex align-items-center justify-content-center">
                            @if($notification->user && $notification->user->profile_photo_path)
                                <img src="{{ $notification->user->profile_photo_url }}" alt="avatar" class="rounded-circle">
                            @else
                                <span>{{ $notification->getAvatarInitials() }}</span>
                            @endif
                        </div>
                        
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">{{ $notification->subject }}</h6>
                                    <p class="mb-1 text-body">{{ \Str::limit(strip_tags($notification->message), 100) }}</p>
                                    <div class="d-flex gap-3">
                                        <small class="text-muted">{{ $notification->formatted_created_date }}</small>
                                        @if($notification->type)
                                            <small class="text-primary">{{ $notification->type->name }}</small>
                                        @endif
                                        {!! $notification->status_badge !!}
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-text-secondary rounded-pill btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                        <i class="ti ti-dots-vertical ti-sm"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @can('notification.show')
                                        <a class="dropdown-item" href="{{ route('notification.show', $notification->id) }}">
                                            <i class="ti ti-eye me-2"></i>Ver detalles
                                        </a>
                                        @endcan
                                        @if(!$notification->is_sent)
                                            @can('notification.edit')
                                            <a class="dropdown-item" href="{{ route('notification.edit', $notification->id) }}">
                                                <i class="ti ti-edit me-2"></i>Editar
                                            </a>
                                            @endcan
                                            @can('notification.send')
                                            <a class="dropdown-item" href="#" onclick="sendNotification({{ $notification->id }})">
                                                <i class="ti ti-send me-2"></i>Enviar ahora
                                            </a>
                                            @endcan
                                        @else
                                            @can('notification.resend')
                                            <a class="dropdown-item" href="#" onclick="resendNotification({{ $notification->id }})">
                                                <i class="ti ti-repeat me-2"></i>Reenviar
                                            </a>
                                            @endcan
                                        @endif
                                        @can('notification.destroy')
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger" href="#" onclick="deleteNotification({{ $notification->id }})">
                                            <i class="ti ti-trash me-2"></i>Eliminar
                                        </a>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                    
                    @if($notifications->count() >= 10)
                    <div class="text-center mt-4">
                        <a href="{{ route('notification-list') }}?contact={{ $collaborator->id }}" class="btn btn-outline-primary">
                            <i class="ti ti-eye me-1"></i>Ver todas las notificaciones
                        </a>
                    </div>
                    @endif
                @else
                    <div class="text-center py-4">
                        <div class="avatar avatar-xl bg-light-secondary rounded-circle mx-auto mb-3">
                            <i class="ti ti-bell-off ti-lg"></i>
                        </div>
                        <h6 class="mb-1">No hay notificaciones</h6>
                        <p class="text-muted mb-3">Este colaborador no tiene notificaciones enviadas.</p>
                        @can('notification.create')
                        <a href="{{ route('notification.create') }}?contact_id={{ $collaborator->id }}" class="btn btn-primary">
                            <i class="ti ti-plus me-1"></i>Enviar primera notificación
                        </a>
                        @endcan
                    </div>
                @endif
            </div>
        </div>
    </div>
    <!--/ Notificaciones Content -->
</div>

<!-- Include Valoration Modal -->
@include('collaborator.partials.valoration-modal')
@endsection 