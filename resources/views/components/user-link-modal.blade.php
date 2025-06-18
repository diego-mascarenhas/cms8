<!-- Modal para vincular usuario -->
<div class="modal fade" id="linkUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vincular Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Tabs -->
                <ul class="nav nav-tabs" id="linkUserTabs" role="tablist">
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

                <div class="tab-content pt-3" id="linkUserTabsContent">
                    <!-- Existing User Tab -->
                    <div class="tab-pane fade show active" id="existing-user" role="tabpanel">
                        <form id="linkExistingUserForm">
                            @csrf
                            <div class="mb-3">
                                <label for="user_search" class="form-label">Buscar Usuario</label>
                                <select id="user_search" name="user_id" class="form-select select2" required>
                                    <option value="">-- Seleccionar usuario --</option>
                                    @foreach(\App\Models\User::whereHas('teams', function($q) { $q->where('team_id', auth()->user()->currentTeam->id); })->orderBy('name')->get() as $user)
                                        <option value="{{ $user->id }}" data-email="{{ $user->email }}" data-role="{{ $user->roles->first()->name ?? 'user' }}">
                                            {{ $user->name }} ({{ $user->email }}) - {{ $user->roles->first()->name ?? 'user' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Vincular Usuario</button>
                            </div>
                        </form>
                    </div>

                    <!-- New User Tab -->
                    <div class="tab-pane fade" id="new-user" role="tabpanel">
                        <form id="createUserForm">
                            @csrf
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="new_user_name" class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" id="new_user_name" name="name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="new_user_email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="new_user_email" name="email" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="new_user_phone" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="new_user_phone" name="phone">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="new_user_role" class="form-label">Rol *</label>
                                    <select class="form-select" id="new_user_role" name="role" required>
                                        <option value="">-- Seleccionar rol --</option>
                                        @foreach(\Spatie\Permission\Models\Role::all() as $role)
                                            <option value="{{ $role->name }}" {{ $role->name === 'collaborator' ? 'selected' : '' }}>
                                                {{ ucfirst($role->name) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="new_user_password" class="form-label">Contraseña temporal *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_user_password" name="password" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="generatePassword()">
                                            <i class="ti ti-refresh ti-xs"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">El usuario deberá cambiar esta contraseña en su primer acceso</small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Crear y Vincular</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Global functions for user linking
window.showLinkUserModal = function(contactId, type = 'contact', name = '', email = '') {
    window.currentContactId = contactId;
    window.currentLinkType = type;
    window.currentName = name;
    window.currentEmail = email;
    console.log(`Opening modal for ${type}:`, contactId);
    
    $('#linkUserModal').modal('show');
}

window.unlinkUser = function(contactId, type = 'contact') {
    console.log(`Unlinking user from ${type}:`, contactId);
    if (confirm('¿Estás seguro de que quieres desvincular este usuario?')) {
        fetch(`/${type}/${contactId}/unlink-user`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Unlink response:', data);
            if (data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: 'Usuario desvinculado correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    alert('Usuario desvinculado correctamente');
                }
                location.reload();
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error al desvincular usuario'
                    });
                } else {
                    alert(data.message || 'Error al desvincular usuario');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al desvincular usuario'
                });
            } else {
                alert('Error al desvincular usuario');
            }
        });
    }
}

window.generatePassword = function() {
    const length = 10;
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%";
    let password = "";
    for (let i = 0, n = charset.length; i < length; ++i) {
        password += charset.charAt(Math.floor(Math.random() * n));
    }
    const passwordField = document.getElementById('new_user_password');
    if (passwordField) {
        passwordField.value = password;
    }
}

$(document).ready(function() {
    // Initialize Select2 when modal is shown
    $('#linkUserModal').on('shown.bs.modal', function () {
        console.log('Modal shown, initializing select2...');
        $('#user_search').select2({
            dropdownParent: $('#linkUserModal'),
            placeholder: 'Buscar usuario...',
            allowClear: true
        });
        
        // Pre-fill data if available
        setTimeout(function() {
            if (window.currentName && document.getElementById('new_user_name')) {
                document.getElementById('new_user_name').value = window.currentName;
            }
            if (window.currentEmail && document.getElementById('new_user_email')) {
                document.getElementById('new_user_email').value = window.currentEmail;
            }
        }, 100);
        
        // Generate password when modal is opened
        generatePassword();
    });

    // Handle existing user linking
    $('#linkExistingUserForm').on('submit', function(e) {
        e.preventDefault();
        console.log('Submitting existing user form...');
        
        const userId = $('#user_search').val();
        if (!userId) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Atención',
                    text: 'Por favor selecciona un usuario'
                });
            } else {
                alert('Por favor selecciona un usuario');
            }
            return;
        }

        const type = window.currentLinkType || 'contact';
        const url = `/${type}/${window.currentContactId}/link-user`;
        console.log('Making request to:', url);
        console.log('Request data:', { user_id: userId, type: type, contactId: window.currentContactId });
        
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            body: JSON.stringify({
                user_id: userId
            })
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            return response.json();
        })
        .then(data => {
            console.log('Link response:', data);
            if (data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: 'Usuario vinculado correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    alert('Usuario vinculado correctamente');
                }
                $('#linkUserModal').modal('hide');
                location.reload();
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error al vincular usuario'
                    });
                } else {
                    alert(data.message || 'Error al vincular usuario');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al vincular usuario'
                });
            } else {
                alert('Error al vincular usuario');
            }
        });
    });

    // Handle new user creation and linking
    $('#createUserForm').on('submit', function(e) {
        e.preventDefault();
        console.log('Submitting create user form...');
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData);

        const type = window.currentLinkType || 'contact';
        const url = `/${type}/${window.currentContactId}/create-and-link-user`;
        console.log('Making create user request to:', url);
        console.log('Create user data:', data);
        
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            console.log('Create response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Create response:', data);
            if (data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: 'Usuario creado y vinculado correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    alert('Usuario creado y vinculado correctamente');
                }
                $('#linkUserModal').modal('hide');
                location.reload();
            } else {
                if (data.errors) {
                    Object.keys(data.errors).forEach(field => {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error de validación',
                                text: data.errors[field][0]
                            });
                        } else {
                            alert('Error: ' + data.errors[field][0]);
                        }
                    });
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Error al crear usuario'
                        });
                    } else {
                        alert(data.message || 'Error al crear usuario');
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al crear usuario'
                });
            } else {
                alert('Error al crear usuario');
            }
        });
    });
});
</script> 