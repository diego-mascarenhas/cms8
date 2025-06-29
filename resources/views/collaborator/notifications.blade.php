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
// JavaScript para funcionalidades futuras si es necesario
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
                    <div class="mb-4 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1 me-3">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <h6 class="mb-0">{{ $notification->subject }}</h6>
                                    @if(!$notification->is_sent)
                                        <span class="bg-warning rounded-circle d-inline-block" style="width: 8px; height: 8px;" title="Pendiente de envío"></span>
                                    @elseif($notification->is_sent && !$notification->is_read)
                                        <span class="bg-primary rounded-circle d-inline-block" style="width: 8px; height: 8px;" title="No leído"></span>
                                    @endif
                                </div>
                                <p class="mb-2 text-body">{{ \Str::limit(strip_tags($notification->message), 150) }}</p>
                                <div class="d-flex gap-3 mb-2">
                                    <small class="text-muted">{{ $notification->formatted_created_date }}</small>
                                    @if($notification->type)
                                        <div class="d-flex align-items-center gap-1">
                                            <small class="text-primary">{{ $notification->type->name }}</small>
                                            <a href="{{ route('notification.show', $notification->id) }}" class="text-primary" title="Ver detalle">
                                                <i class="ti ti-eye ti-xs"></i>
                                            </a>
                                        </div>
                                    @endif
                                    {!! $notification->status_badge !!}
                                </div>
                            </div>
                            @can('notification.show')
                            <a href="{{ route('notification.show', $notification->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="ti ti-eye me-1"></i>Ver detalle
                            </a>
                            @endcan
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