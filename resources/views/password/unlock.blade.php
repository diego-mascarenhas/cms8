@extends('layouts/layoutMaster')

@section('title', 'Desbloquear cofre de contraseñas')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-lock me-2"></i>Desbloquear cofre de contraseñas</h5>
            </div>
            <div class="card-body">
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <form method="POST" action="{{ route('passwords.unlock') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="master_key" class="form-label">Clave maestra</label>
                        <input id="master_key" name="master_key" type="password" class="form-control @error('master_key') is-invalid @enderror" required autofocus>
                        @error('master_key')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    @if($masterKeyHint !== '')
                        <div class="alert alert-secondary py-2">
                            <small class="text-muted">Pista: {{ $masterKeyHint }}</small>
                        </div>
                    @endif

                    <button class="btn btn-primary" type="submit">
                        <i class="ti ti-lock-open-2 me-1"></i>Desbloquear por 15 minutos
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
