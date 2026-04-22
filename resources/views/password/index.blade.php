@extends('layouts/layoutMaster')

@section('title', 'Contraseñas')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Contraseñas</h4>
        <p class="text-muted">Cofre seguro del equipo con enlaces públicos de un solo uso.</p>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <form method="POST" action="{{ route('passwords.lock') }}">
            @csrf
            <button class="btn btn-outline-secondary" type="submit">
                <i class="ti ti-lock me-1"></i>Bloquear cofre
            </button>
        </form>
        @can('create', \App\Models\TeamPassword::class)
            <a href="{{ route('passwords.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i>Agregar contraseña
            </a>
        @endcan
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card mb-4">
    <div class="card-header border-bottom">
        <div id="passwords-toolbar" class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
            <div class="w-100 w-md-auto" style="max-width: 340px;">
                <label class="form-label mb-1" for="filter_password_enterprise_id">Empresa</label>
                <select id="filter_password_enterprise_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach($enterprises as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div id="passwords-search-slot" class="w-100 w-md-auto d-flex justify-content-md-end"></div>
        </div>
    </div>
    <div class="card-body">
        {!! $dataTable->table(['class' => 'table table-hover']) !!}
    </div>
</div>

<div class="modal fade" id="unlockVaultModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Desbloquear cofre</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Ingresa tu clave maestra para continuar.</p>
                <div class="mb-1">
                    <label class="form-label" for="unlockVaultMasterKey">Clave maestra</label>
                    <input
                        type="password"
                        id="unlockVaultMasterKey"
                        class="form-control"
                        autocomplete="current-password"
                    >
                    <small id="unlockVaultError" class="text-danger d-none mt-1"></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="unlockVaultConfirmBtn">Desbloquear</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
{!! $dataTable->scripts() !!}
<script>
$(function () {
    const moveSearchToToolbar = function () {
        const searchFilter = $('#team-passwords-table_filter');
        const searchSlot = $('#passwords-search-slot');
        if (searchFilter.length && searchSlot.length && !searchSlot.find('#team-passwords-table_filter').length) {
            searchFilter.addClass('mb-0');
            searchFilter.find('label').removeClass('w-100').addClass('mb-0 d-flex align-items-center justify-content-md-end gap-2');
            searchFilter.find('input[type="search"]').addClass('form-control-sm').attr('placeholder', 'Buscar').css('width', '176px');
            searchSlot.append(searchFilter);
        }
    };

    moveSearchToToolbar();
    setTimeout(moveSearchToToolbar, 250);

    $(document).on('change', '#filter_password_enterprise_id', function () {
        var table = window.LaravelDataTables?.['team-passwords-table'];
        if (table) {
            table.ajax.reload();
        }
    });
});

async function revealPassword(passwordId) {
    let response = await fetch(`/password/${passwordId}/reveal`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        }
    });

    let payload = await response.json();
    if (response.status === 423 && payload.requires_unlock) {
        const unlocked = await unlockVaultInline();
        if (!unlocked) return;
        response = await fetch(`/password/${passwordId}/reveal`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        });
        payload = await response.json();
    }

    if (!response.ok || !payload.password) {
        alert(payload.message || 'No se pudo mostrar la contraseña');
        return;
    }

    await navigator.clipboard.writeText(payload.password);
    alert('Contraseña copiada al portapapeles');
}

async function createShare(passwordId) {
    let response = await fetch(`/password/${passwordId}/share`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        }
    });

    let payload = await response.json();
    if (response.status === 423 && payload.requires_unlock) {
        const unlocked = await unlockVaultInline();
        if (!unlocked) return;
        response = await fetch(`/password/${passwordId}/share`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        });
        payload = await response.json();
    }

    if (!response.ok || !payload.url) {
        alert(payload.message || 'No se pudo generar la URL compartida');
        return;
    }

    await navigator.clipboard.writeText(payload.url);
    alert('URL pública de un solo uso copiada. Puedes enviarla por email o WhatsApp.');
}

async function confirmDeletePassword(passwordId) {
    const result = await Swal.fire({
        title: '¿Eliminar contraseña?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        showDenyButton: false,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    });

    if (!result.isConfirmed) {
        return;
    }

    const response = await fetch(`{{ route('passwords.destroy', ':passwordId') }}`.replace(':passwordId', passwordId), {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        }
    });

    if (!response.ok) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo eliminar la contraseña.'
        });
        return;
    }

    Swal.fire({
        icon: 'success',
        title: 'Eliminada',
        text: 'Contraseña eliminada correctamente.',
        timer: 1500,
        showConfirmButton: false
    });

    const table = window.LaravelDataTables?.['team-passwords-table'];
    if (table) {
        table.ajax.reload(null, false);
    }
}

async function unlockVaultInline() {
    const modalElement = document.getElementById('unlockVaultModal');
    const inputElement = document.getElementById('unlockVaultMasterKey');
    const errorElement = document.getElementById('unlockVaultError');
    const confirmButton = document.getElementById('unlockVaultConfirmBtn');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

    return await new Promise((resolve) => {
        let settled = false;

        const clearError = () => {
            errorElement.textContent = '';
            errorElement.classList.add('d-none');
        };

        const showError = (message) => {
            errorElement.textContent = message;
            errorElement.classList.remove('d-none');
        };

        const settle = (result) => {
            if (settled) {
                return;
            }

            settled = true;
            cleanup();
            resolve(result);
        };

        const handleHidden = () => {
            settle(false);
        };

        const handleConfirm = async () => {
            clearError();
            const masterKey = inputElement.value.trim();
            if (!masterKey) {
                showError('La clave maestra es obligatoria.');
                inputElement.focus();
                return;
            }

            confirmButton.disabled = true;
            try {
                const response = await fetch(`{{ route('passwords.unlock') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ master_key: masterKey })
                });

                if (!response.ok) {
                    const payload = await response.json();
                    showError(payload.message || 'Clave maestra inválida.');
                    return;
                }

                settle(true);
                modal.hide();
            } catch (error) {
                showError('No se pudo desbloquear el cofre. Inténtalo nuevamente.');
            } finally {
                confirmButton.disabled = false;
            }
        };

        const handleInput = () => {
            clearError();
        };

        const handleEnter = (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                handleConfirm();
            }
        };

        const cleanup = () => {
            modalElement.removeEventListener('hidden.bs.modal', handleHidden);
            confirmButton.removeEventListener('click', handleConfirm);
            inputElement.removeEventListener('input', handleInput);
            inputElement.removeEventListener('keydown', handleEnter);
            inputElement.value = '';
            clearError();
        };

        modalElement.addEventListener('hidden.bs.modal', handleHidden);
        confirmButton.addEventListener('click', handleConfirm);
        inputElement.addEventListener('input', handleInput);
        inputElement.addEventListener('keydown', handleEnter);

        clearError();
        inputElement.value = '';
        modal.show();
        setTimeout(() => inputElement.focus(), 150);
    });
}
</script>
@endsection
