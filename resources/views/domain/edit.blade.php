@extends('layouts/contentNavbarLayout')

@section('title', 'Edit Domain')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold py-3 mb-0">
        <span class="text-muted fw-light">Domains /</span> Edit {{ $domain->domain }}
    </h4>
    <div>
        <a href="{{ route('domain.show', $domain->id) }}" class="btn btn-secondary">
            <i class="ti ti-arrow-left me-1"></i>
            Back to Details
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">Edit Domain</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('domain.update', $domain->id) }}">
                    @csrf
                    @method('PUT')
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="domain" class="form-label">Domain Name</label>
                            <input type="text" class="form-control @error('domain') is-invalid @enderror" id="domain" name="domain" value="{{ old('domain', $domain->domain) }}" required placeholder="example.com">
                            @error('domain')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label for="server_url" class="form-label">Server</label>
                            <select class="form-select @error('server_url') is-invalid @enderror" id="server_url" name="server_url" required>
                                <option value="">Select Server</option>
                                @foreach ($servers as $server)
                                    <option value="{{ $server->server_url }}" {{ old('server_url', $domain->server_url) == $server->server_url ? 'selected' : '' }}>
                                        {{ $server->server_url }}
                                    </option>
                                @endforeach
                            </select>
                            @error('server_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username', $domain->username) }}" required placeholder="cPanel username">
                            @error('username')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label for="plan" class="form-label">Plan</label>
                            <input type="text" class="form-control @error('plan') is-invalid @enderror" id="plan" name="plan" value="{{ old('plan', $domain->plan) }}" placeholder="Hosting plan">
                            @error('plan')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="site_type" class="form-label">Site Type</label>
                            <select class="form-select @error('site_type') is-invalid @enderror" id="site_type" name="site_type">
                                <option value="">Select Site Type</option>
                                <option value="WordPress" {{ old('site_type', $domain->site_type) == 'WordPress' ? 'selected' : '' }}>WordPress</option>
                                <option value="Laravel" {{ old('site_type', $domain->site_type) == 'Laravel' ? 'selected' : '' }}>Laravel</option>
                                <option value="Static" {{ old('site_type', $domain->site_type) == 'Static' ? 'selected' : '' }}>Static HTML</option>
                                <option value="Other" {{ old('site_type', $domain->site_type) == 'Other' ? 'selected' : '' }}>Other</option>
                            </select>
                            @error('site_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label for="php_version" class="form-label">PHP Version</label>
                            <select class="form-select @error('php_version') is-invalid @enderror" id="php_version" name="php_version">
                                <option value="">Select PHP Version</option>
                                <option value="8.2" {{ old('php_version', $domain->php_version) == '8.2' ? 'selected' : '' }}>PHP 8.2</option>
                                <option value="8.1" {{ old('php_version', $domain->php_version) == '8.1' ? 'selected' : '' }}>PHP 8.1</option>
                                <option value="8.0" {{ old('php_version', $domain->php_version) == '8.0' ? 'selected' : '' }}>PHP 8.0</option>
                                <option value="7.4" {{ old('php_version', $domain->php_version) == '7.4' ? 'selected' : '' }}>PHP 7.4</option>
                                <option value="7.3" {{ old('php_version', $domain->php_version) == '7.3' ? 'selected' : '' }}>PHP 7.3</option>
                            </select>
                            @error('php_version')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3" placeholder="Additional notes about this domain">{{ old('notes', $domain->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="suspended" name="suspended" {{ old('suspended', $domain->suspended) ? 'checked' : '' }}>
                                <label class="form-check-label" for="suspended">Suspended</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="needs_update" name="needs_update" {{ old('needs_update', $domain->needs_update) ? 'checked' : '' }}>
                                <label class="form-check-label" for="needs_update">Needs Update</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="is_working" name="is_working" {{ old('is_working', $domain->is_working) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_working">Is Working</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Update Domain</button>
                            <a href="{{ route('domain.show', $domain->id) }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection 