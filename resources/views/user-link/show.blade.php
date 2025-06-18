@extends('layouts/layoutMaster')

@section('title', 'Vincular Usuario')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">
                {{ ucfirst($type) }}/
            </span> 
            {{ $contact->name }} / Vincular Usuario
        </h4>
        <p class="text-muted">Vincular un usuario existente o crear uno nuevo</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route($type === 'contact' ? 'contact.show' : 'collaborator.show', $contact->id) }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <!-- Contact/Collaborator Info Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-lg me-3">
                        <img class="rounded-circle" src="https://ui-avatars.com/api/?format=svg&name={{ urlencode($contact->name) }}" alt="{{ $contact->name }}">
                    </div>
                    <div>
                        <h5 class="mb-1">{{ $contact->name }}</h5>
                        <p class="mb-0 text-muted">{{ $contact->email }}</p>
                        @if($contact->user_id)
                            @php $linkedUser = \App\Models\User::find($contact->user_id); @endphp
                            @if($linkedUser)
                                <span class="badge bg-label-success mt-1">
                                    <i class="ti ti-link ti-xs me-1"></i>
                                    Vinculado a {{ $linkedUser->name }}
                                </span>
                            @endif
                        @else
                            <span class="badge bg-label-warning mt-1">
                                <i class="ti ti-unlink ti-xs me-1"></i>
                                Sin vincular
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="existing-user-tab" data-bs-toggle="tab" data-bs-target="#existing-user" type="button" role="tab">
                            <i class="ti ti-user ti-xs me-1"></i>Usuario Existente
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="new-user-tab" data-bs-toggle="tab" data-bs-target="#new-user" type="button" role="tab">
                            <i class="ti ti-user-plus ti-xs me-1"></i>Crear Usuario
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Existing User Tab -->
                    <div class="tab-pane fade show active" id="existing-user" role="tabpanel">
                        <form action="{{ route('user-link.process', [$type, $contact->id]) }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-md-8">
                                    <label for="user_id" class="form-label">Seleccionar Usuario *</label>
                                    <select id="user_id" name="user_id" class="form-select select2 @error('user_id') is-invalid @enderror" required>
                                        <option value="">-- Seleccionar usuario --</option>
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                                {{ $user->name }} ({{ $user->email }}) - {{ $user->roles->first()->name ?? 'user' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('user_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="ti ti-link ti-xs me-1"></i>Vincular Usuario
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- New User Tab -->
                    <div class="tab-pane fade" id="new-user" role="tabpanel">
                        <form action="{{ route('user-link.create', [$type, $contact->id]) }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Nombre *</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           id="name" name="name" value="{{ old('name', $contact->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                           id="email" name="email" value="{{ old('email', $contact->email) }}" required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control @error('phone') is-invalid @enderror" 
                                           id="phone" name="phone" value="{{ old('phone', $contact->phone) }}">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label">Rol *</label>
                                    <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                                        <option value="">-- Seleccionar rol --</option>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->name }}" 
                                                {{ old('role', $type === 'collaborator' ? 'collaborator' : 'client') === $role->name ? 'selected' : '' }}>
                                                {{ ucfirst($role->name) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('role')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="password" class="form-label">Contraseña temporal *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                               id="password" name="password" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="generatePassword()">
                                            <i class="ti ti-refresh ti-xs"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">El usuario deberá cambiar esta contraseña en su primer acceso</small>
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="pt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-user-plus ti-xs me-1"></i>Crear y Vincular Usuario
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize Select2
    $('#user_id').select2({
        placeholder: 'Buscar usuario...',
        allowClear: true
    });
    
    // Generate password on page load
    generatePassword();
});

function generatePassword() {
    const length = 10;
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%";
    let password = "";
    for (let i = 0, n = charset.length; i < length; ++i) {
        password += charset.charAt(Math.floor(Math.random() * n));
    }
    document.getElementById('password').value = password;
}
</script>
@endpush 