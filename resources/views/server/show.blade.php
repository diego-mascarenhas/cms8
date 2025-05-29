@extends('layouts.contentNavbarLayout')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">Servers/</span> {{ $server->name }}
        </h4>
        <p class="text-muted">
            Gestiona tus servidores
        </p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('server.index') }}" class="btn btn-secondary waves-effect waves-light">
            <i class="ti ti-arrow-left me-1"></i>Back to Servers
        </a>
        <a href="{{ route('server.edit', $server->id) }}" class="btn btn-primary waves-effect waves-light">
            <i class="ti ti-edit me-1"></i>Edit Server
        </a>
        @if($server->control_panel === 'cpanel' && $server->hasToken())
            <button type="button" class="btn btn-info waves-effect waves-light" id="test-connection-btn">
                <i class="ti ti-world me-1"></i>Test Connection
            </button>
            <button type="button" class="btn btn-success waves-effect waves-light" id="sync-domains-action-btn">
                <i class="ti ti-refresh me-1"></i>Sync Domains
            </button>
        @endif
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Server Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table">
                            <tr>
                                <th style="width: 40%;">Name</th>
                                <td>{{ $server->name }}</td>
                            </tr>
                            <tr>
                                <th>IP Address</th>
                                <td>{{ $server->ip ?: 'Not specified' }}</td>
                            </tr>
                            <tr>
                                <th>Server URL</th>
                                <td>{{ $server->server_url }}</td>
                            </tr>
                            <tr>
                                <th>Username</th>
                                <td>{{ $server->username }}</td>
                            </tr>
                            <tr>
                                <th>Operating System</th>
                                <td>{{ $server->operating_system ?: 'Not specified' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table">
                            <tr>
                                <th style="width: 40%;">Control Panel</th>
                                <td>{{ $server->control_panel_name }}</td>
                            </tr>
                            @if($server->team)
                            <tr>
                                <th>Team</th>
                                <td>{{ $server->team->name }}</td>
                            </tr>
                            @endif
                            <tr>
                                <th>Token Status</th>
                                <td>
                                    @if($server->hasToken())
                                        <span class="badge bg-success">Configured</span>
                                    @else
                                        <span class="badge bg-warning">Not configured</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <span class="badge bg-{{ $server->status_id->color() }}">
                                        {{ $server->status_id->name() }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Created</th>
                                <td>{{ $server->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                            <tr>
                                <th>Last Updated</th>
                                <td>{{ $server->updated_at->format('M d, Y H:i') }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @if($server->data && isset($server->data['last_connection_test']))
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Connection Test Results</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <th style="width: 40%;">Last Test</th>
                                <td>{{ \Carbon\Carbon::parse($server->data['last_connection_test'])->format('M d, Y H:i:s') }}</td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    @if($server->data['connection_status'] === 'Success')
                                        <span class="badge bg-success">{{ $server->data['connection_status'] }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ $server->data['connection_status'] }}</span>
                                    @endif
                                </td>
                            </tr>
                            
                            @if(isset($server->data['api_version']))
                            <tr>
                                <th>WHM Version</th>
                                <td>{{ $server->data['api_version'] }}</td>
                            </tr>
                            @endif
                            
                            @if(isset($server->data['build']))
                            <tr>
                                <th>Build</th>
                                <td>{{ $server->data['build'] }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            @if(isset($server->data['server_hostname']))
                            <tr>
                                <th style="width: 40%;">Server Hostname</th>
                                <td>{{ $server->data['server_hostname'] }}</td>
                            </tr>
                            @endif
                            
                            @if(isset($server->data['test_response_time']))
                            <tr>
                                <th>Response Time</th>
                                <td>{{ $server->data['test_response_time'] }}</td>
                            </tr>
                            @endif
                            
                            @if(isset($server->data['error_code']))
                            <tr>
                                <th>Error Code</th>
                                <td><span class="badge bg-warning">{{ $server->data['error_code'] }}</span></td>
                            </tr>
                            @endif
                            
                            @if(isset($server->data['connection_error']))
                            <tr>
                                <th>Error</th>
                                <td class="text-danger">{{ $server->data['connection_error'] }}</td>
                            </tr>
                            @endif
                            
                            @if(isset($server->data['error_message']))
                            <tr>
                                <th>Error Message</th>
                                <td class="text-danger">{{ $server->data['error_message'] }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($server->control_panel === 'cpanel')
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">cPanel Domains</h5>
            </div>
            <div class="card-body">
                @if(!$server->hasToken())
                    <div class="alert alert-warning">
                        <i class="ti ti-alert-triangle me-2"></i>
                        Token not configured. Please edit the server to add an encrypted token for cPanel access.
                    </div>
                @elseif($cPanelError)
                    <div class="alert alert-danger">
                        <i class="ti ti-exclamation-circle me-2"></i>
                        Error connecting to cPanel: {{ $cPanelError }}
                    </div>
                @elseif($cPanelDomains && $cPanelDomains->count() > 0)
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="domain-search" placeholder="Search domains, users, or plans...">
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <small class="text-muted ms-auto">
                                <i class="ti ti-info-circle me-1"></i>
                                Total domains: <span id="total-domains">{{ $cPanelDomains->count() }}</span>
                                <span id="filtered-info" style="display: none;"> | Showing: <span id="visible-domains"></span></span>
                            </small>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover" id="domains-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Domain</th>
                                    <th>User</th>
                                    <th>Plan</th>
                                    <th>Status</th>
                                    <th>Disk Usage</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cPanelDomains as $domain)
                                <tr>
                                    <td>
                                        <strong class="text-primary">{{ $domain['domain'] }}</strong>
                                    </td>
                                    <td>
                                        <span class="text-muted">{{ $domain['user'] }}</span>
                                    </td>
                                    <td>{{ $domain['plan'] ?: 'N/A' }}</td>
                                    <td>
                                        @if($domain['suspended'])
                                            <span class="badge bg-danger">Suspended</span>
                                        @else
                                            <span class="badge bg-success">Active</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $domain['disk_used'] ?: '0' }} MB
                                            @if($domain['disk_limit'])
                                                / {{ $domain['disk_limit'] }} MB
                                            @endif
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $domain['email'] ?: 'N/A' }}</small>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif($cPanelDomains)
                    <div class="alert alert-info">
                        <i class="ti ti-info-circle me-2"></i>
                        No domains found on this cPanel server.
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="ti ti-loader ti-spin me-2"></i>
                        Click "Sync Domains" to load domains from cPanel server.
                    </div>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sync Domains functionality (header button only)
    const syncDomainsActionBtn = document.getElementById('sync-domains-action-btn');
    
    function handleSyncDomains(btn) {
        const originalText = btn.innerHTML;
        
        // Set loading state
        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Syncing...';
        
        fetch(`{{ route('server.syncDomains', $server->id) }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showAlert('success', data.message);
                // Reload page to show updated domains
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showAlert('danger', data.message || 'Failed to sync domains');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred while syncing domains');
        })
        .finally(() => {
            // Reset button state
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }
    
    if (syncDomainsActionBtn) {
        syncDomainsActionBtn.addEventListener('click', function() {
            handleSyncDomains(this);
        });
    }
    
    // Test Connection functionality
    const testConnectionBtn = document.getElementById('test-connection-btn');
    if (testConnectionBtn) {
        testConnectionBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const btn = this;
            const originalText = btn.innerHTML;
            
            // Set loading state
            btn.disabled = true;
            btn.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Testing...';
            
            fetch(`{{ route('server.testConnection', $server->id) }}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                }
            })
            .then(response => {
                if (response.ok) {
                    showAlert('success', 'Connection test initiated. Check server status for results.');
                } else {
                    showAlert('danger', 'Failed to initiate connection test');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while testing connection');
            })
            .finally(() => {
                // Reset button state
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    }
    
    // Domain search functionality
    const domainSearch = document.getElementById('domain-search');
    const domainsTable = document.getElementById('domains-table');
    const totalDomainsSpan = document.getElementById('total-domains');
    const filteredInfo = document.getElementById('filtered-info');
    const visibleDomainsSpan = document.getElementById('visible-domains');
    
    if (domainSearch && domainsTable) {
        domainSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = domainsTable.querySelectorAll('tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const domain = row.cells[0].textContent.toLowerCase();
                const user = row.cells[1].textContent.toLowerCase();
                const plan = row.cells[2].textContent.toLowerCase();
                const email = row.cells[5].textContent.toLowerCase();
                
                if (domain.includes(searchTerm) || 
                    user.includes(searchTerm) || 
                    plan.includes(searchTerm) || 
                    email.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            if (searchTerm.length > 0) {
                filteredInfo.style.display = 'inline';
                visibleDomainsSpan.textContent = visibleCount;
            } else {
                filteredInfo.style.display = 'none';
            }
        });
    }
    
    function showAlert(type, message) {
        // Create alert element
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            <i class="ti ti-${type === 'success' ? 'check' : 'exclamation-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insert at the top of the container
        const container = document.querySelector('.container');
        container.insertBefore(alert, container.firstChild);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
});
</script>
@endpush 