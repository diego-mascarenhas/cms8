@extends('layouts.contentNavbarLayout')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1>Edit Server</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('server.index') }}" class="btn btn-secondary">
                <i class="ti ti-arrow-left me-1"></i> Back to Server
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('server.update', $server->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                           id="name" name="name" value="{{ old('name', $server->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="ip" class="form-label">IP Address</label>
                    <input type="text" class="form-control @error('ip') is-invalid @enderror" 
                           id="ip" name="ip" value="{{ old('ip', $server->ip) }}" placeholder="198.51.100.1">
                    @error('ip')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="server_url" class="form-label">Server URL</label>
                    <input type="text" class="form-control @error('server_url') is-invalid @enderror" 
                           id="server_url" name="server_url" value="{{ old('server_url', $server->server_url) }}" required>
                    @error('server_url')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control @error('username') is-invalid @enderror" 
                           id="username" name="username" value="{{ old('username', $server->username) }}" required>
                    @error('username')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="status_id" class="form-label">Status</label>
                    <select class="form-select @error('status_id') is-invalid @enderror" 
                            id="status_id" name="status_id" required>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" 
                                {{ old('status_id', $server->status_id->value) == $status->value ? 'selected' : '' }}>
                                {{ $status->name() }}
                            </option>
                        @endforeach
                    </select>
                    @error('status_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary">Update Server</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection 