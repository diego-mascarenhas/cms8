@extends('layouts/layoutMaster')

@section('title', 'Passwords Security')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Team Settings/</span> Passwords Security</h4>
        <p class="text-muted">Configure the master key used to unlock your team password vault</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('team-settings.index', $team) }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back to Settings') }}
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
                <h5 class="card-title mb-0"><i class="ti ti-key me-2"></i>{{ $hasMasterKey ? 'Rotate Master Key' : 'Set Master Key' }}</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('team-settings.passwords.update', $team) }}" novalidate>
                    @csrf
                    @method('PUT')

                    @if ($hasMasterKey)
                        <div class="mb-3">
                            <label for="current_master_key" class="form-label">Current Master Key</label>
                            <input id="current_master_key" name="current_master_key" type="password" class="form-control @error('current_master_key') is-invalid @enderror">
                            @error('current_master_key')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="new_master_key" class="form-label">{{ $hasMasterKey ? 'New Master Key' : 'Master Key' }}</label>
                        <input id="new_master_key" name="new_master_key" type="password" class="form-control @error('new_master_key') is-invalid @enderror">
                        @error('new_master_key')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Use at least 8 characters. This key is required to unlock the vault.</div>
                    </div>

                    <div class="mb-3">
                        <label for="new_master_key_confirmation" class="form-label">Confirm {{ $hasMasterKey ? 'New ' : '' }}Master Key</label>
                        <input id="new_master_key_confirmation" name="new_master_key_confirmation" type="password" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label for="master_key_hint" class="form-label">Master Key Hint (optional)</label>
                        <input id="master_key_hint" name="master_key_hint" type="text" class="form-control @error('master_key_hint') is-invalid @enderror" value="{{ old('master_key_hint', $masterKeyHint) }}" maxlength="120">
                        @error('master_key_hint')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Do not include the actual key in this hint.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i>{{ $hasMasterKey ? 'Rotate Master Key' : 'Save Master Key' }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-3">Vault status</h6>
                <p class="mb-1">
                    <span class="badge {{ $hasMasterKey ? 'bg-success' : 'bg-warning' }}">
                        {{ $hasMasterKey ? 'Protected' : 'Pending setup' }}
                    </span>
                </p>
                @if ($rotationAt)
                    <small class="text-muted">Last rotation: {{ \Carbon\Carbon::parse($rotationAt)->diffForHumans() }}</small>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
