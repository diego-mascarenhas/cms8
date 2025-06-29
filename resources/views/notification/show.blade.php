@extends('layouts/layoutMaster')

@section('title', __('Notification Details'))

@section('content')
<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Notifications') }}/</span> {{ $notification->subject }}</h4>
        <p class="text-muted">{{ __('Notification details') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @if(!$notification->is_sent)
            @can('notification.edit')
            <a href="{{ route('notification.edit', $notification->id) }}" class="btn btn-primary waves-effect waves-light">
                <i class="ti ti-edit me-1"></i>Editar notificación
            </a>
            @endcan
            @can('notification.send')
            <form action="{{ route('notification.send', $notification->id) }}" method="POST" style="display: inline;">
                @csrf
                <button type="submit" class="btn btn-success waves-effect waves-light" 
                        onclick="return confirm('¿Estás seguro de que quieres enviar esta notificación?')">
                    <i class="ti ti-send me-1"></i>Enviar notificación
                </button>
            </form>
            @endcan
        @else
            @can('notification.resend')
            <form action="{{ route('notification.resend', $notification->id) }}" method="POST" style="display: inline;">
                @csrf
                <button type="submit" class="btn btn-warning waves-effect waves-light" 
                        onclick="return confirm('¿Estás seguro de que quieres reenviar esta notificación?')">
                    <i class="ti ti-repeat me-1"></i>Reenviar notificación
                </button>
            </form>
            @endcan
        @endif
        @can('notification.edit')
        <a href="{{ route('notification-list') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>Volver al listado
        </a>
        @endcan
    </div>
</div>

<!-- Main Content -->
<div class="row">
    <!-- Notification Details -->
    <div class="col-md-8">
        <div class="card mb-4">
            <h5 class="card-header">
                <i class="ti ti-bell me-2"></i>{{ __('Notification Information') }}
            </h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Tipo de notificación</label>
                        <p class="text-body">{{ $notification->type->name }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Estado</label>
                        <div>
                            {!! $notification->status_badge !!}
                            @if($notification->is_sent)
                                <span class="ms-2 text-muted">{{ $notification->formatted_sent_date }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Contacto</label>
                        <p class="text-body">
                            <a href="{{ route('contact.show', $notification->contact->id) }}" class="text-decoration-none">
                                {{ $notification->contact->name }} {{ $notification->contact->surname }}
                            </a>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Email del contacto</label>
                        <p class="text-body">{{ $notification->contact->email }}</p>
                    </div>
                    @if($notification->reference)
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Referencia</label>
                        <p class="text-body">{{ $notification->reference }}</p>
                    </div>
                    @endif
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Fecha de creación</label>
                        <p class="text-body">{{ $notification->formatted_created_date }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Message Content -->
        <div class="card mb-4">
            <h5 class="card-header">
                <i class="ti ti-message me-2"></i>{{ __('Message Content') }}
            </h5>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-medium">Asunto</label>
                    <p class="text-body">{{ $notification->subject }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium">Mensaje</label>
                    <div class="border rounded p-3 bg-light">
                        {!! $notification->formatted_message !!}
                    </div>
                </div>
            </div>
        </div>

        @if($notification->is_sent && $notification->email_metadata)
        <!-- Email Metadata -->
        <div class="card mb-4">
            <h5 class="card-header">
                <i class="ti ti-info-circle me-2"></i>{{ __('Delivery Information') }}
            </h5>
            <div class="card-body">
                @php
                    $metadata = json_decode($notification->email_metadata, true);
                @endphp
                <div class="row g-3">
                    @if(isset($metadata['message_id']))
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Message ID</label>
                        <p class="text-body font-monospace">{{ $metadata['message_id'] }}</p>
                    </div>
                    @endif
                    @if(isset($metadata['to']))
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Enviado a</label>
                        <p class="text-body">{{ $metadata['to'] }}</p>
                    </div>
                    @endif
                    @if(isset($metadata['from']))
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Enviado desde</label>
                        <p class="text-body">{{ $metadata['from'] }}</p>
                    </div>
                    @endif
                    @if(isset($metadata['sent_at']))
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Hora de envío</label>
                        <p class="text-body">{{ $metadata['sent_at'] }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Sidebar -->
    <div class="col-md-4">
        <!-- Quick Actions -->
        <div class="card mb-4">
            <h5 class="card-header">
                <i class="ti ti-settings me-2"></i>{{ __('Actions') }}
            </h5>
            <div class="card-body">
                <div class="d-grid gap-2">
                    @if(!$notification->is_sent)
                        @can('notification.edit')
                        <a href="{{ route('notification.edit', $notification->id) }}" class="btn btn-outline-primary">
                            <i class="ti ti-edit me-2"></i>Editar notificación
                        </a>
                        @endcan
                        @can('notification.send')
                        <form action="{{ route('notification.send', $notification->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-success w-100" 
                                    onclick="return confirm('¿Estás seguro de que quieres enviar esta notificación?')">
                                <i class="ti ti-send me-2"></i>Enviar ahora
                            </button>
                        </form>
                        @endcan
                        @can('notification.destroy')
                        <form action="{{ route('notification.destroy', $notification->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger w-100" 
                                    onclick="return confirm('¿Estás seguro de que quieres eliminar esta notificación?')">
                                <i class="ti ti-trash me-2"></i>Eliminar
                            </button>
                        </form>
                        @endcan
                    @else
                        @can('notification.resend')
                        <form action="{{ route('notification.resend', $notification->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-warning w-100" 
                                    onclick="return confirm('¿Estás seguro de que quieres reenviar esta notificación?')">
                                <i class="ti ti-repeat me-2"></i>Reenviar
                            </button>
                        </form>
                        @endcan
                    @endif
                    
                    <a href="{{ route('notification-list') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-arrow-left me-2"></i>Volver al listado
                    </a>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="card mb-4">
            <h5 class="card-header">
                <i class="ti ti-user me-2"></i>{{ __('Contact Information') }}
            </h5>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="avatar me-2">
                        <span class="avatar-initial rounded-circle bg-label-primary">
                            {{ strtoupper(substr($notification->contact->name, 0, 1)) }}
                        </span>
                    </div>
                    <div>
                        <h6 class="mb-0">{{ $notification->contact->name }} {{ $notification->contact->surname }}</h6>
                        <small class="text-muted">{{ $notification->contact->email }}</small>
                    </div>
                </div>
                
                @if($notification->contact->phone)
                <div class="mb-2">
                    <small class="text-muted">Teléfono:</small>
                    <div>{{ $notification->contact->phone }}</div>
                </div>
                @endif
                
                @if($notification->contact->company)
                <div class="mb-2">
                    <small class="text-muted">Empresa:</small>
                    <div>{{ $notification->contact->company }}</div>
                </div>
                @endif

                <div class="mt-3">
                    <a href="{{ route('contact.show', $notification->contact->id) }}" class="btn btn-sm btn-outline-primary w-100">
                        <i class="ti ti-external-link me-1"></i>Ver contacto completo
                    </a>
                </div>
            </div>
        </div>

        <!-- Notification Type Info -->
        <div class="card">
            <h5 class="card-header">
                <i class="ti ti-template me-2"></i>{{ __('Template Information') }}
            </h5>
            <div class="card-body">
                <div class="mb-2">
                    <small class="text-muted">Tipo:</small>
                    <div>{{ $notification->type->name }}</div>
                </div>
                @if($notification->type->description)
                <div class="mb-2">
                    <small class="text-muted">Descripción:</small>
                    <div class="small">{{ $notification->type->description }}</div>
                </div>
                @endif
                <div class="mb-2">
                    <small class="text-muted">Personalizable:</small>
                    <div>
                        @if($notification->type->is_customizable)
                            <span class="badge bg-success">Sí</span>
                        @else
                            <span class="badge bg-secondary">No</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 