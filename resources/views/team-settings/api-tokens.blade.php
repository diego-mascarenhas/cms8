@extends('layouts/layoutMaster')

@section('title', 'API Access Tokens')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Team Settings/</span> API Access Tokens</h4>
        <p class="text-muted">Generate and manage team API tokens for external access</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('team-settings.index', $team) }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back to Settings') }}
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        @if (session('new_token'))
            <div class="alert alert-warning">
                <h5><i class="ti ti-alert-triangle me-2"></i>New API Token Generated</h5>
                <p class="mb-2">Please copy and store this token securely. You won't be able to see it again.</p>
                <div class="bg-light p-3 rounded">
                    <code id="new-token" style="font-size: 14px; word-break: break-all;">{{ session('new_token') }}</code>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('#new-token')">
                        <i class="ti ti-copy"></i>
                    </button>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="ti ti-key me-2"></i>API Access Token
                </h5>
            </div>
            <div class="card-body">
                @if($currentToken)
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Token Status</label>
                                <p class="form-text">
                                    <span class="badge bg-success">Active</span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Token Name</label>
                                <p class="form-text">{{ $tokenName }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Abilities</label>
                                <p class="form-text">
                                    @if($tokenAbilities === '*')
                                        <span class="badge bg-warning">All Abilities</span>
                                    @else
                                        @foreach(explode(',', $tokenAbilities) as $ability)
                                            <span class="badge bg-info">{{ ucfirst(trim($ability)) }}</span>
                                        @endforeach
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Created</label>
                                <p class="form-text">
                                    {{ $tokenCreated ? \Carbon\Carbon::parse($tokenCreated)->diffForHumans() : 'Unknown' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <h6><i class="ti ti-info-circle me-2"></i>API Usage Instructions</h6>
                        <p class="mb-2">Use this token to authenticate API requests to your team's endpoints:</p>
                        <ul class="mb-0">
                            <li><strong>Base URL:</strong> <code>{{ url('/api/team') }}</code></li>
                            <li><strong>Authentication:</strong> Include header <code>Authorization: Bearer YOUR_TOKEN</code></li>
                            <li><strong>Team ID:</strong> <code>{{ $team->id }}</code></li>
                        </ul>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="button" class="btn btn-primary" onclick="revealToken()">
                            <i class="ti ti-eye me-1"></i>View Token
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="showEditTokenModal()">
                            <i class="ti ti-edit me-1"></i>Edit Name & Abilities
                        </button>
                        <button type="button" class="btn btn-danger" onclick="confirmRevokeToken()">
                            <i class="ti ti-trash me-1"></i>Revoke Token
                        </button>
                        <button type="button" class="btn btn-warning" onclick="showGenerateForm()">
                            <i class="ti ti-refresh me-1"></i>Generate New Token
                        </button>
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="ti ti-key-off" style="font-size: 3rem; color: #ccc;"></i>
                        <h5 class="mt-3">No API Token Generated</h5>
                        <p class="text-muted">Generate an API token to enable external access to your team's data</p>
                        <button type="button" class="btn btn-primary" onclick="showGenerateForm()">
                            <i class="ti ti-plus me-1"></i>Generate API Token
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Generate Token Modal -->
<div class="modal fade" id="generateTokenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate API Token</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('team-settings.generate-api-token', $team) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="token-name" class="form-label">Token Name</label>
                        <input type="text" class="form-control" id="token-name" name="name" 
                               value="{{ old('name', $tokenName) }}" required>
                        <div class="form-text">Give your token a descriptive name</div>
                    </div>
                    <div class="mb-3">
                        <label for="token-abilities" class="form-label">Token Abilities</label>
                        <select class="form-select" id="token-abilities" name="abilities" required>
                            <option value="*" {{ old('abilities', $tokenAbilities) === '*' ? 'selected' : '' }}>
                                All Abilities
                            </option>
                            <option value="read" {{ old('abilities', $tokenAbilities) === 'read' ? 'selected' : '' }}>
                                Read Only
                            </option>
                            <option value="write" {{ old('abilities', $tokenAbilities) === 'write' ? 'selected' : '' }}>
                                Write Only
                            </option>
                            <option value="read,write" {{ old('abilities', $tokenAbilities) === 'read,write' ? 'selected' : '' }}>
                                Read & Write
                            </option>
                        </select>
                        <div class="form-text">Choose what this token can do</div>
                    </div>
                    
                    @if($currentToken)
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-triangle me-2"></i>
                            <strong>Warning:</strong> Generating a new token will revoke the current token.
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Token</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Token Modal -->
<div class="modal fade" id="editTokenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Token Name & Abilities</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('team-settings.update-api-token', $team) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-token-name" class="form-label">Token Name</label>
                        <input type="text" class="form-control" id="edit-token-name" name="name"
                               value="{{ old('name', $tokenName) }}" required>
                        <div class="form-text">Give your token a descriptive name</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit-token-abilities" class="form-label">Token Abilities</label>
                        <select class="form-select" id="edit-token-abilities" name="abilities" required>
                            <option value="*" {{ old('abilities', $tokenAbilities) === '*' ? 'selected' : '' }}>
                                All Abilities
                            </option>
                            <option value="read" {{ old('abilities', $tokenAbilities) === 'read' ? 'selected' : '' }}>
                                Read Only
                            </option>
                            <option value="write" {{ old('abilities', $tokenAbilities) === 'write' ? 'selected' : '' }}>
                                Write Only
                            </option>
                            <option value="read,write" {{ old('abilities', $tokenAbilities) === 'read,write' ? 'selected' : '' }}>
                                Read & Write
                            </option>
                        </select>
                        <div class="form-text">Choose what this token can do</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reveal Token Modal -->
<div class="modal fade" id="revealTokenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">API Token</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="reveal-token-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 mb-0">Loading token...</p>
                </div>
                <div id="reveal-token-error" class="alert alert-danger d-none"></div>
                <div id="reveal-token-content" class="d-none">
                    <p class="text-muted small">Copy this token and store it securely.</p>
                    <div class="bg-light p-3 rounded">
                        <code id="revealed-token" style="font-size: 14px; word-break: break-all;"></code>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('#revealed-token')">
                            <i class="ti ti-copy"></i> Copy
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Revoke Token Form -->
<form id="revokeTokenForm" action="{{ route('team-settings.revoke-api-token', $team) }}" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-script')
<script>
function showGenerateForm() {
    new bootstrap.Modal(document.getElementById('generateTokenModal')).show();
}

function showEditTokenModal() {
    new bootstrap.Modal(document.getElementById('editTokenModal')).show();
}

function confirmRevokeToken() {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will permanently revoke the current API token. Any applications using this token will lose access.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, revoke it!',
        cancelButtonText: 'Cancel',
        customClass: {
            confirmButton: 'btn btn-danger me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('revokeTokenForm').submit();
        }
    });
}

function copyToClipboard(element) {
    const text = document.querySelector(element).textContent;
    navigator.clipboard.writeText(text).then(function() {
        Swal.fire({
            icon: 'success',
            title: 'Copied!',
            text: 'Token copied to clipboard',
            timer: 1500,
            showConfirmButton: false
        });
    });
}

function revealToken() {
    const modal = new bootstrap.Modal(document.getElementById('revealTokenModal'));
    document.getElementById('reveal-token-loading').classList.remove('d-none');
    document.getElementById('reveal-token-error').classList.add('d-none');
    document.getElementById('reveal-token-content').classList.add('d-none');
    modal.show();

    const revealUrl = '{{ route('team-settings.reveal-api-token', $team) }}';
    fetch(revealUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        }
    })
    .then(response => {
        return response.json().then(data => ({ ok: response.ok, data }));
    })
    .then(({ ok, data }) => {
        document.getElementById('reveal-token-loading').classList.add('d-none');
        if (ok && data.token) {
            document.getElementById('revealed-token').textContent = data.token;
            document.getElementById('reveal-token-content').classList.remove('d-none');
        } else {
            document.getElementById('reveal-token-error').textContent = (data && data.error) || 'Could not load token';
            document.getElementById('reveal-token-error').classList.remove('d-none');
        }
    })
    .catch(() => {
        document.getElementById('reveal-token-loading').classList.add('d-none');
        document.getElementById('reveal-token-error').textContent = 'Failed to load token';
        document.getElementById('reveal-token-error').classList.remove('d-none');
    });
}
</script>
@endsection 