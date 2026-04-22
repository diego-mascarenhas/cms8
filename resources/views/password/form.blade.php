@extends('layouts/layoutMaster')

@section('title', 'Contraseñas')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Contraseñas/</span> {{ isset($data->id) ? 'Editar' : 'Crear' }}</h4>
        <p class="text-muted">Guarda credenciales de forma segura para tu equipo.</p>
    </div>
    @if(isset($data->id) && auth()->user()->can('delete', $data))
        <div class="mt-3 mt-md-0">
            <button type="button" class="btn btn-label-danger" onclick="confirmDeleteFromEdit()">
                <i class="ti ti-trash me-1"></i>Eliminar
            </button>
            <form id="deletePasswordForm" method="POST" action="{{ route('passwords.destroy', $data) }}" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        </div>
    @endif
</div>

<div class="card mb-4">
    <h5 class="card-header">Registro de contraseña</h5>
    <form class="card-body" method="POST" action="{{ isset($data->id) ? route('passwords.update', $data) : route('passwords.store') }}">
        @csrf
        @if(isset($data->id))
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label">Nombre (*)</label>
                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $data->name ?? '') }}" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="enterprise_id" class="form-label">Empresa</label>
                <select id="enterprise_id" name="enterprise_id" class="form-select @error('enterprise_id') is-invalid @enderror">
                    <option value="">Ninguna</option>
                    @foreach($enterprises as $id => $name)
                        <option value="{{ $id }}" {{ (string) old('enterprise_id', $data->enterprise_id ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                @error('enterprise_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="username" class="form-label">Usuario</label>
                <input type="text" id="username" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username', $data->username ?? '') }}">
                @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">{{ isset($data->id) ? 'Déjala vacía para conservar la contraseña actual.' : 'Opcional: déjala vacía si este registro solo tendrá notas o metadatos.' }}</div>
            </div>

            <div class="col-md-12">
                <label for="url" class="form-label">URL</label>
                <input type="url" id="url" name="url" class="form-control @error('url') is-invalid @enderror" value="{{ old('url', $data->url ?? '') }}">
                @error('url') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-12">
                <label for="notes" class="form-label">Notas</label>
                <textarea id="notes" name="notes" rows="4" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $data->notes ?? '') }}</textarea>
                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="pt-4">
            <button type="submit" class="btn btn-primary me-2">Guardar</button>
            <a href="{{ route('passwords.index') }}" class="btn btn-label-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

@section('page-script')
<script>
function confirmDeleteFromEdit() {
    Swal.fire({
        title: '¿Eliminar contraseña?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        showDenyButton: false,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deletePasswordForm').submit();
        }
    });
}
</script>
@endsection
