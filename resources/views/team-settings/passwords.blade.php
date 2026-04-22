@extends('layouts/layoutMaster')

@section('title', 'Seguridad de contraseñas')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Configuración del equipo/</span> Seguridad de contraseñas</h4>
        <p class="text-muted">Configura la clave maestra para desbloquear el cofre de contraseñas de tu equipo</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('team-settings.index', $team) }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>Volver a Configuración
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="ti ti-key me-2"></i>{{ $hasMasterKey ? 'Rotar clave maestra' : 'Configurar clave maestra' }}</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('team-settings.passwords.update', $team) }}" novalidate>
                    @csrf
                    @method('PUT')

                    @if ($hasMasterKey)
                        <div class="mb-3">
                            <label for="current_master_key" class="form-label">Clave maestra actual</label>
                            <input id="current_master_key" name="current_master_key" type="password" class="form-control @error('current_master_key') is-invalid @enderror">
                            @error('current_master_key')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="new_master_key" class="form-label">{{ $hasMasterKey ? 'Nueva clave maestra' : 'Clave maestra' }}</label>
                        <input id="new_master_key" name="new_master_key" type="password" class="form-control @error('new_master_key') is-invalid @enderror">
                        @error('new_master_key')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Usa al menos 8 caracteres. Esta clave es necesaria para desbloquear el cofre.</div>
                    </div>

                    <div class="mb-3">
                        <label for="new_master_key_confirmation" class="form-label">Confirmar {{ $hasMasterKey ? 'nueva ' : '' }}clave maestra</label>
                        <input id="new_master_key_confirmation" name="new_master_key_confirmation" type="password" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label for="master_key_hint" class="form-label">Pista de clave maestra (opcional)</label>
                        <input id="master_key_hint" name="master_key_hint" type="text" class="form-control @error('master_key_hint') is-invalid @enderror" value="{{ old('master_key_hint', $masterKeyHint) }}" maxlength="120">
                        @error('master_key_hint')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">No incluyas la clave real en esta pista.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i>{{ $hasMasterKey ? 'Rotar clave maestra' : 'Guardar clave maestra' }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-3">Estado del cofre</h6>
                <p class="mb-1">
                    <span class="badge {{ $hasMasterKey ? 'bg-success' : 'bg-warning' }}">
                        {{ $hasMasterKey ? 'Protegido' : 'Pendiente de configuración' }}
                    </span>
                </p>
                @if ($rotationAt)
                    <small class="text-muted">Última rotación: {{ \Carbon\Carbon::parse($rotationAt)->diffForHumans() }}</small>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
