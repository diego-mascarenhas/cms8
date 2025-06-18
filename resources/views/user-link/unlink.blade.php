@extends('layouts/layoutMaster')

@section('title', 'Desvincular Usuario')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">
                {{ ucfirst($type) }}/
            </span> 
            {{ $contact->name }} / Desvincular Usuario
        </h4>
        <p class="text-muted">Confirmar desvinculación de usuario</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route($type === 'contact' ? 'contact.show' : 'collaborator.show', $contact->id) }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <!-- Current Link Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="ti ti-link ti-sm me-2"></i>
                    Vinculación Actual
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Contact/Collaborator Info -->
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-lg me-3">
                                <img class="rounded-circle" src="https://ui-avatars.com/api/?format=svg&name={{ urlencode($contact->name) }}" alt="{{ $contact->name }}">
                            </div>
                            <div>
                                <h6 class="mb-1">{{ $contact->name }}</h6>
                                <p class="mb-0 text-muted">{{ $contact->email }}</p>
                                <span class="badge bg-label-primary">{{ ucfirst($type) }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Arrow -->
                    <div class="col-md-1 d-flex align-items-center justify-content-center">
                        <i class="ti ti-arrow-right text-muted"></i>
                    </div>
                    
                    <!-- User Info -->
                    <div class="col-md-5">
                        @if($linkedUser)
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-lg me-3">
                                    <img class="rounded-circle" src="https://ui-avatars.com/api/?format=svg&name={{ urlencode($linkedUser->name) }}" alt="{{ $linkedUser->name }}">
                                </div>
                                <div>
                                    <h6 class="mb-1">{{ $linkedUser->name }}</h6>
                                    <p class="mb-0 text-muted">{{ $linkedUser->email }}</p>
                                    @if($linkedUser->roles->count() > 0)
                                        <span class="badge bg-label-info">{{ $linkedUser->roles->first()->name }}</span>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="text-center">
                                <i class="ti ti-user-x text-muted mb-2" style="font-size: 2rem;"></i>
                                <p class="text-muted mb-0">Usuario no encontrado</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Card -->
        <div class="card">
            <div class="card-header bg-label-warning">
                <h5 class="mb-0 text-warning">
                    <i class="ti ti-alert-triangle ti-sm me-2"></i>
                    Confirmar Desvinculación
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <i class="ti ti-info-circle me-2"></i>
                    <div>
                        <strong>¿Estás seguro?</strong><br>
                        Esta acción desvinculará el usuario <strong>{{ $linkedUser ? $linkedUser->name : 'desconocido' }}</strong> 
                        del {{ $type }} <strong>{{ $contact->name }}</strong>.
                        <br><br>
                        <small class="text-muted">
                            • El usuario seguirá existiendo en el sistema<br>
                            • Podrás vincular otro usuario en el futuro<br>
                            • Esta acción se puede revertir
                        </small>
                    </div>
                </div>

                <div class="d-flex justify-content-between pt-3">
                    <a href="{{ route($type === 'contact' ? 'contact.show' : 'collaborator.show', $contact->id) }}" 
                       class="btn btn-outline-secondary">
                        <i class="ti ti-x me-1"></i>Cancelar
                    </a>
                    
                    <form action="{{ route('user-unlink.process', [$type, $contact->id]) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-danger">
                            <i class="ti ti-unlink me-1"></i>Confirmar Desvinculación
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 