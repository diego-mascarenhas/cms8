@extends('layouts/layoutMaster')

@section('title', 'Servidores')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/form-layouts.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Servidores/</span> {{ isset($data->id) ? 'Editar' : 'Crear' }}</h4>
        <p class="text-muted">Administra tus servidores de hosting</p>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">Servidor</h5>
    <form class="card-body" action="{{ isset($data->id) ? route('server.update', $data->id) : route('server.store') }}" method="POST">
        @csrf
        @if(isset($data->id))
            @method('PUT')
        @endif
        
        <div class="row g-3">
            <div class="col-md-6">
                <x-input-general id="name" label="Nombre (*)" value="{{ old('name', $data->name ?? '') }}" />
            </div>
            <div class="col-md-6">
                <x-input-general id="ip" label="Dirección IP" value="{{ old('ip', $data->ip ?? '') }}" placeholder="198.51.100.1" />
            </div>

            <div class="col-md-6">
                <x-input-general id="server_url" label="URL del servidor (*)" value="{{ old('server_url', $data->server_url ?? '') }}" />
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1" style="min-height: 2.25rem;">
                        <label for="control_panel" class="form-label mb-0">Panel de control (*)</label>
                    </div>
                    <select class="form-select @error('control_panel') is-invalid @enderror" id="control_panel" name="control_panel">
                        <option value="none" {{ old('control_panel', $data->control_panel ?? 'none') == 'none' ? 'selected' : '' }}>
                            Ninguno
                        </option>
                        <option value="cpanel" {{ old('control_panel', $data->control_panel ?? '') == 'cpanel' ? 'selected' : '' }}>
                            cPanel
                        </option>
                        <option value="plesk" {{ old('control_panel', $data->control_panel ?? '') == 'plesk' ? 'selected' : '' }}>
                            Plesk
                        </option>
                    </select>
                    @error('control_panel')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            @php($authMode = old('auth_mode', $data->data['auth_mode'] ?? 'cpanel_user'))
            <input type="hidden" name="auth_mode" value="{{ $authMode }}">

            <div class="col-md-6">
                <x-input-general id="username" label="Usuario (*)" value="{{ old('username', $data->username ?? '') }}" />
            </div>
            <div class="col-md-6">
                <x-input-general
                    id="encrypted_token"
                    type="password"
                    label="{{ isset($data->id) ? 'Contraseña' : 'Contraseña (*)' }}"
                    value="{{ old('encrypted_token', isset($data->id) ? '' : '') }}"
                />
                @if(isset($data->id))
                    <div class="form-text">Dejar en blanco para mantener la credencial actual.</div>
                @endif
            </div>
        </div>
        
        <div class="pt-4">
            <div class="col-12 d-flex">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                <button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('server.index') }}'">Cancelar</button>
            </div>
        </div>
    </form>
</div>

@endsection
