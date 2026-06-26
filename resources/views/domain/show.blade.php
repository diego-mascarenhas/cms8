@extends('layouts/contentNavbarLayout')

@section('title', 'Domain Details')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold py-3 mb-0">
        <span class="text-muted fw-light">Domains /</span> {{ $domain->domain }}
    </h4>
    <div>
        <a href="{{ route('domain.edit', $domain->id) }}" class="btn btn-primary me-2">
            <i class="ti ti-edit me-1"></i>
            Edit
        </a>
        <a href="{{ route('hosting.index') }}" class="btn btn-secondary">
            <i class="ti ti-arrow-left me-1"></i>
            Back to List
        </a>
    </div>
</div>

@include('hosting.partials.provisioning-notice')

<!-- Status cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Status</h5>
                @if($domain->suspended)
                    <span class="badge bg-label-danger">Suspended</span>
                @else
                    <span class="badge bg-label-success">Active</span>
                @endif
                
                <div class="mt-3">
                    <form action="{{ route('domain.toggle-suspension', $domain->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm {{ $domain->suspended ? 'btn-outline-success' : 'btn-outline-danger' }}">
                            {{ $domain->suspended ? 'Activate' : 'Suspend' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Server</h5>
                <p class="mb-0">{{ $domain->server ? $domain->server->server_url : 'N/A' }}</p>
                <small class="text-muted">Username: {{ $domain->username }}</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Site Info</h5>
                <p class="mb-0">Type: {{ $domain->site_type ?? 'N/A' }}</p>
                <p class="mb-0">PHP: {{ $domain->php_version ?? 'N/A' }}</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Plan</h5>
                <p class="mb-0">{{ $domain->plan ?? 'N/A' }}</p>
                <div class="mt-3">
                    <form action="{{ route('domain.refresh', $domain->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="ti ti-refresh me-1"></i> Refresh Data
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Main domain details -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between">
                <h5 class="card-title mb-0">Domain Details</h5>
                <small class="text-muted">Last updated: {{ $domain->updated_at->diffForHumans() }}</small>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Domain Name:</div>
                    <div class="col-md-8">{{ $domain->domain }}</div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Server:</div>
                    <div class="col-md-8">{{ $domain->server ? $domain->server->server_url : 'N/A' }}</div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Username:</div>
                    <div class="col-md-8">{{ $domain->username }}</div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Plan:</div>
                    <div class="col-md-8">{{ $domain->plan ?? 'N/A' }}</div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Site Type:</div>
                    <div class="col-md-8">{{ $domain->site_type ?? 'N/A' }}</div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">PHP Version:</div>
                    <div class="col-md-8">{{ $domain->php_version ?? 'N/A' }}</div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Web IP:</div>
                    <div class="col-md-8">{{ $domain->web_ip ?? 'N/A' }}</div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Mail IP:</div>
                    <div class="col-md-8">{{ $domain->mail_ip ?? 'N/A' }}</div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">SSL Status:</div>
                    <div class="col-md-8">
                        @if($domain->hasSsl())
                            <span class="badge bg-label-success">Valid</span>
                            <small class="ms-2">Expires: {{ $domain->ssl_expiry }}</small>
                        @else
                            <span class="badge bg-label-danger">Invalid</span>
                        @endif
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Status Flags:</div>
                    <div class="col-md-8">
                        @if($domain->suspended)
                            <span class="badge bg-label-danger me-1">Suspended</span>
                        @endif
                        
                        @if($domain->needs_update)
                            <span class="badge bg-label-warning me-1">Needs Update</span>
                        @endif
                        
                        @if(!$domain->is_working)
                            <span class="badge bg-label-danger me-1">Not Working</span>
                        @endif
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Notes:</div>
                    <div class="col-md-8">{{ $domain->notes ?? 'No notes available' }}</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- DNS and control panel management -->
    <div class="col-md-4">
        @if($controlPanelError)
            <div class="alert alert-warning mb-4">
                <i class="ti ti-alert-triangle me-1"></i>{{ $controlPanelError }}
            </div>
        @endif

        @if(($controlPanelType ?? null) === 'cpanel')
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Change Plan</h5>
            </div>
            <div class="card-body">
                @if($usesAccountAuth ?? false)
                    <p class="text-muted mb-0">Plan changes require WHM API access. This server uses cPanel account credentials only.</p>
                @else
                <form action="{{ route('domain.change-plan', $domain->id) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="plan" class="form-label">Hosting plan</label>
                        <select name="plan" id="plan" class="form-select" required>
                            <option value="">Select a plan</option>
                            @foreach($availablePlans as $planName)
                                <option value="{{ $planName }}" @selected($domain->plan === $planName)>{{ $planName }}</option>
                            @endforeach
                            @if($domain->plan && !in_array($domain->plan, $availablePlans, true))
                                <option value="{{ $domain->plan }}" selected>{{ $domain->plan }} (current)</option>
                            @endif
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Update plan</button>
                </form>
                @endif
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Email Accounts</h5>
            </div>
            <div class="card-body">
                @if(!empty($emailAccounts))
                    <div class="table-responsive mb-3">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Disk</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($emailAccounts as $account)
                                    <tr>
                                        <td>{{ $account['email'] }}</td>
                                        <td>{{ $account['diskused'] ?? '0' }} / {{ $account['diskquota'] ?? '∞' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted">No email accounts loaded.</p>
                @endif

                <form action="{{ route('domain.email-password', $domain->id) }}" method="POST">
                    @csrf
                    <div class="mb-2">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">New password</label>
                        <input type="password" name="password" id="password" class="form-control form-control-sm" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">Change password</button>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">MX Records</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('domain.mx-records', $domain->id) }}" method="POST">
                    @csrf
                    <div id="mx-records-list">
                        @forelse($mxRecords as $index => $record)
                            <div class="row g-2 mb-2 mx-record-row">
                                <div class="col-4">
                                    <input type="number" name="mx_records[{{ $index }}][priority]" class="form-control form-control-sm" value="{{ $record['priority'] }}" min="0" required>
                                </div>
                                <div class="col-8">
                                    <input type="text" name="mx_records[{{ $index }}][target]" class="form-control form-control-sm" value="{{ $record['target'] }}" required>
                                </div>
                            </div>
                        @empty
                            <div class="row g-2 mb-2 mx-record-row">
                                <div class="col-4">
                                    <input type="number" name="mx_records[0][priority]" class="form-control form-control-sm" value="10" min="0" required>
                                </div>
                                <div class="col-8">
                                    <input type="text" name="mx_records[0][target]" class="form-control form-control-sm" placeholder="mail.example.com" required>
                                </div>
                            </div>
                        @endforelse
                    </div>
                    <button type="button" class="btn btn-label-secondary btn-sm mb-3" id="add-mx-record">Add MX</button>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Save MX records</button>
                </form>
            </div>
        </div>
        @elseif(($controlPanelType ?? null) === 'plesk')
        <div class="card mb-4">
            <div class="card-body">
                <p class="text-muted mb-0">Plesk account management will be available in a future update. You can already register Plesk servers.</p>
            </div>
        </div>
        @endif

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">DNS Records</h5>
            </div>
            <div class="card-body">
                @if(!empty($domain->dns_records))
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Name</th>
                                    <th>Content</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($domain->dns_records as $record)
                                    <tr>
                                        <td>{{ $record['type'] ?? '-' }}</td>
                                        <td>{{ $record['name'] ?? '-' }}</td>
                                        <td>{{ $record['content'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted">No DNS records available</p>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const list = document.getElementById('mx-records-list');
    const addButton = document.getElementById('add-mx-record');

    if (!list || !addButton) {
        return;
    }

    addButton.addEventListener('click', function () {
        const index = list.querySelectorAll('.mx-record-row').length;
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 mx-record-row';
        row.innerHTML = `
            <div class="col-4">
                <input type="number" name="mx_records[${index}][priority]" class="form-control form-control-sm" value="10" min="0" required>
            </div>
            <div class="col-8">
                <input type="text" name="mx_records[${index}][target]" class="form-control form-control-sm" placeholder="mail.example.com" required>
            </div>
        `;
        list.appendChild(row);
    });
});
</script>
@endpush
@endsection 