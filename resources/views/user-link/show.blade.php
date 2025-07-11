@extends('layouts/layoutMaster')

@section('title', 'Vincular Usuario')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="card shadow-sm">
            <div class="card-header text-center bg-light">
                <h4 class="mb-1 mt-3">
                    <span class="text-muted fw-light">{{ ucfirst($type) }}/</span> 
                    {{ $contact->name }} / Vincular Usuario
                </h4>
                <p class="text-muted">Vincular o crear un usuario para este {{ $type }}</p>
            </div>

            <div class="card-body p-4">
                <!-- Contact/Collaborator Info -->
                <div class="text-center mb-4">
                    <div class="avatar avatar-xl mx-auto mb-3">
                        <img class="rounded-circle" 
                             src="https://ui-avatars.com/api/?format=svg&name={{ urlencode($contact->name) }}" 
                             alt="{{ $contact->name }}">
                    </div>
                    <h5 class="mb-2">{{ $contact->name }}</h5>
                    <p class="text-muted mb-0">{{ $contact->email ?? 'Sin email' }}</p>
                    <span class="badge bg-label-primary mt-2">{{ ucfirst($type) }}</span>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-pills nav-fill mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="existing-user-tab" data-bs-toggle="pill" 
                                data-bs-target="#existing-user" type="button" role="tab">
                            <i class="ti ti-user-search me-2"></i>Usuario Existente
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="create-user-tab" data-bs-toggle="pill" 
                                data-bs-target="#create-user" type="button" role="tab">
                            <i class="ti ti-user-plus me-2"></i>Crear Usuario
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Existing User Tab -->
                    <div class="tab-pane fade show active" id="existing-user" role="tabpanel">
                        <form method="POST" action="{{ route('user-link.process', [$type, $contact->id]) }}">
                            @csrf
                            <div class="mb-4">
                                <label for="user_id" class="form-label">Buscar Usuario</label>
                                <select id="user_id" name="user_id" class="form-select" required>
                                    <option value="">Seleccionar usuario...</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" 
                                                data-email="{{ $user->email }}" 
                                                data-role="{{ $user->roles->first()->name ?? 'Sin rol' }}">
                                            {{ $user->name }} - {{ $user->email }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('user_id')
                                    <div class="text-danger mt-1"><small>{{ $message }}</small></div>
                                @enderror
                            </div>

                            <!-- User Preview -->
                            <div id="user-preview" class="d-none mb-4">
                                <div class="alert alert-info d-flex align-items-center">
                                    <div class="avatar avatar-md me-3">
                                        <div id="preview-avatar"></div>
                                    </div>
                                    <div>
                                        <h6 class="mb-1" id="preview-name"></h6>
                                        <small class="text-muted d-block" id="preview-email"></small>
                                        <span class="badge bg-label-primary mt-1" id="preview-role"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-center gap-3">
                                <a href="{{ $type === 'contact' ? route('contact.show', $contact->id) : route('collaborator.show', $contact->id) }}" 
                                   class="btn btn-outline-secondary">
                                    <i class="ti ti-x me-1"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-link me-1"></i>Vincular Usuario
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Create User Tab -->
                    <div class="tab-pane fade" id="create-user" role="tabpanel">
                        <form method="POST" action="{{ route('user-link.create', [$type, $contact->id]) }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Nombre *</label>
                                    <input type="text" id="name" name="name" class="form-control" 
                                           value="{{ old('name', $contact->name) }}" required>
                                    @error('name')
                                        <div class="text-danger mt-1"><small>{{ $message }}</small></div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="{{ old('email', $contact->email) }}" required>
                                    @error('email')
                                        <div class="text-danger mt-1"><small>{{ $message }}</small></div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Contraseña *</label>
                                    <div class="input-group">
                                        <input type="password" id="password" name="password" class="form-control" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="generatePassword()">
                                            <i class="ti ti-refresh"></i>
                                        </button>
                                    </div>
                                    @error('password')
                                        <div class="text-danger mt-1"><small>{{ $message }}</small></div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label">Rol *</label>
                                    <select id="role" name="role" class="form-select" required>
                                        <option value="">Seleccionar rol...</option>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->name }}" 
                                                    {{ old('role', $type === 'collaborator' ? 'collaborator' : 'client') === $role->name ? 'selected' : '' }}>
                                                {{ ucfirst($role->name) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('role')
                                        <div class="text-danger mt-1"><small>{{ $message }}</small></div>
                                    @enderror
                                </div>
                            </div>

                            <div class="d-flex justify-content-center gap-3 mt-4">
                                <a href="{{ $type === 'contact' ? route('contact.show', $contact->id) : route('collaborator.show', $contact->id) }}" 
                                   class="btn btn-outline-secondary">
                                    <i class="ti ti-x me-1"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="ti ti-user-plus me-1"></i>Crear y Vincular
                                </button>
                            </div>
                        </form>
                    </div>
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

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize Select2
    $('#user_id').select2({
        placeholder: 'Buscar usuario...',
        allowClear: true,
        width: '100%'
    });

    // User preview when selecting
    $('#user_id').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const userName = selectedOption.text().split(' - ')[0];
        const userEmail = selectedOption.data('email');
        const userRole = selectedOption.data('role');
        
        if ($(this).val()) {
            $('#preview-name').text(userName);
            $('#preview-email').text(userEmail);
            $('#preview-role').text(userRole);
            $('#preview-avatar').html(`<img class="rounded-circle w-100" src="https://ui-avatars.com/api/?format=svg&name=${encodeURIComponent(userName)}" alt="${userName}">`);
            $('#user-preview').removeClass('d-none');
        } else {
            $('#user-preview').addClass('d-none');
        }
    });
});

function generatePassword() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('password').value = password;
}
</script>
@endpush 