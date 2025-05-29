@extends('layouts/layoutMaster')

@section('title', __('Servers'))

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
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Servers') }}/</span> {{ isset($data->id) ? __('Edit') : __('Create') }}</h4>
        <p class="text-muted">{{ __('Manage your servers') }}</p>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">Servers</h5>
    <form class="card-body" action="{{ route('server.store') }}" method="POST">
        @csrf
        <input type="hidden" name="id" value="{{ $data->id ?? '' }}">
        
        <div class="row g-3">
            <div class="col-md-6">
                <x-input-general id="name" label="Name (*)" value="{{ old('name', $data->name ?? '') }}" />
            </div>
            <div class="col-md-6">
                <x-input-general id="ip" label="IP Address" value="{{ old('ip', $data->ip ?? '') }}" placeholder="198.51.100.1" />
            </div>

            <div class="col-md-6">
                <x-input-general id="server_url" label="Server URL (*)" value="{{ old('server_url', $data->server_url ?? '') }}" />
            </div>
            <div class="col-md-6">
                <x-input-general id="username" label="Username (*)" value="{{ old('username', $data->username ?? '') }}" />
            </div>

            <div class="col-md-6">
                <x-input-general id="operating_system" label="Operating System" value="{{ old('operating_system', $data->operating_system ?? '') }}" placeholder="Linux, Windows, etc." />
            </div>
            <div class="col-md-6">
                <label for="control_panel" class="form-label">Control Panel (*)</label>
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
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            @if(isset($teams) && $teams->count() > 0)
            <div class="col-md-6">
                <label for="team_id" class="form-label">Team</label>
                <select class="form-select @error('team_id') is-invalid @enderror" id="team_id" name="team_id">
                    <option value="">Select a team (optional)</option>
                    @foreach($teams as $team)
                        <option value="{{ $team->id }}" 
                            {{ old('team_id', $data->team_id ?? '') == $team->id ? 'selected' : '' }}>
                            {{ $team->name }}
                        </option>
                    @endforeach
                </select>
                @error('team_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            @endif

            <div class="col-md-6">
                <x-input-select id="status_id" label="Status (*)" :options="$statuses" value="{{ old('status_id', $data->status_id->value ?? '1') }}" />
            </div>

            <div class="col-md-12">
                <label for="encrypted_token" class="form-label">Encrypted Token</label>
                <textarea class="form-control @error('encrypted_token') is-invalid @enderror" 
                          id="encrypted_token" name="encrypted_token" rows="3"
                          placeholder="Token encriptado para autenticación">{{ old('encrypted_token', $data->encrypted_token ?? '') }}</textarea>
                @error('encrypted_token')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
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