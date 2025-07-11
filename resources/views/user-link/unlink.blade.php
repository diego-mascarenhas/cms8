@extends('layouts/layoutMaster')

@section('title', 'Desvincular Usuario')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header text-center bg-light">
                <h4 class="mb-1 mt-3">
                    <span class="text-muted fw-light">{{ ucfirst($type) }}/</span> 
                    {{ $contact->name }} / Desvincular Usuario
                </h4>
                <p class="text-muted">Confirmar desvinculación de usuario</p>
            </div>
            
            <div class="card-body p-4">
                <!-- Current Link Section -->
                <div class="d-flex justify-content-center align-items-center mb-4">
                    <div class="text-center">
                        <div class="avatar avatar-lg">
                            <img class="rounded-circle" 
                                 src="https://ui-avatars.com/api/?format=svg&name={{ urlencode($contact->name) }}" 
                                 alt="{{ $contact->name }}">
                        </div>
                        <div class="fw-medium mt-2">{{ $contact->name }}</div>
                        <small class="d-block text-muted">{{ ucfirst($type) }}</small>
                    </div>
                    
                    <div class="mx-4">
                        <i class="ti ti-x text-danger" style="font-size: 2rem;"></i>
                    </div>
                    
                    <div class="text-center">
                        <div class="fw-medium">{{ $linkedUser->name }}</div>
                        <small class="d-block text-muted">{{ $linkedUser->email }}</small>
                        @if($linkedUser->roles->count() > 0)
                            <span class="badge bg-label-primary mt-1">{{ $linkedUser->roles->first()->name }}</span>
                        @endif
                    </div>
                </div>

                <!-- Warning Section -->
                <div class="alert alert-warning d-flex align-items-start" role="alert">
                    <i class="ti ti-alert-triangle me-2 mt-1"></i>
                    <div>
                        <h6 class="mb-1">¿Estás seguro?</h6>
                        <p class="mb-2">Esta acción desvinculará el usuario <strong>{{ $linkedUser->name }}</strong> del {{ $type }} <strong>{{ $contact->name }}</strong>.</p>
                        <ul class="mb-0 ps-3">
                            <li>El usuario seguirá existiendo en el sistema</li>
                            <li>Podrás vincular otro usuario en el futuro</li>
                            <li>Esta acción se puede revertir</li>
                        </ul>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-center gap-3 mt-4">
                    <a href="{{ $type === 'contact' ? route('contact.show', $contact->id) : route('collaborator.show', $contact->id) }}" 
                       class="btn btn-outline-secondary">
                        <i class="ti ti-x me-1"></i>Cancelar
                    </a>
                    <form method="POST" 
                          action="{{ route('user-unlink.process', [$type, $contact->id]) }}" 
                          class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-danger">
                            <i class="ti ti-unlink me-1"></i>Confirmar Desvinculación
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Back Button -->
        <div class="text-center mt-3">
            <a href="{{ $type === 'contact' ? route('contact.show', $contact->id) : route('collaborator.show', $contact->id) }}" 
               class="btn btn-link">
                <i class="ti ti-arrow-left me-1"></i>Volver
            </a>
        </div>
    </div>
</div>
@endsection 