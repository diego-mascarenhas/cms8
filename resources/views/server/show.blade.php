@extends('layouts.contentNavbarLayout')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1>Server Details</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('server.index') }}" class="btn btn-secondary">
                <i class="ti ti-arrow-left me-1"></i> Back to Servers
            </a>
            <a href="{{ route('server.edit', $server->id) }}" class="btn btn-primary">
                <i class="ti ti-edit me-1"></i> Edit Server
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Server Information</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <tr>
                            <th style="width: 30%;">Name</th>
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
                        <tr>
                            <th>Control Panel</th>
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

            @if($server->data && isset($server->data['last_connection_test']))
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Connection Test Results</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th style="width: 30%;">Last Test</th>
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
                        
                        @if(isset($server->data['server_hostname']))
                        <tr>
                            <th>Server Hostname</th>
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
            @endif

            @if($server->control_panel === 'cpanel')
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">cPanel Domains</h5>
                    @if($server->hasToken())
                        <button type="button" class="btn btn-sm btn-primary" id="sync-domains-btn">
                            <i class="ti ti-refresh me-1"></i> Sync Domains
                        </button>
                    @endif
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
                        <div class="mb-3">
                            <input type="text" class="form-control form-control-sm" id="domain-search" placeholder="Search domains...">
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-sm" id="domains-table">
                                <thead>
                                    <tr>
                                        <th>Domain</th>
                                        <th>User</th>
                                        <th>Plan</th>
                                        <th>Status</th>
                                        <th>Disk Used</th>
                                        <th>Email</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($cPanelDomains as $domain)
                                    <tr>
                                        <td>
                                            <strong>{{ $domain['domain'] }}</strong>
                                        </td>
                                        <td>{{ $domain['user'] }}</td>
                                        <td>{{ $domain['plan'] ?: 'N/A' }}</td>
                                        <td>
                                            @if($domain['suspended'])
                                                <span class="badge bg-danger">Suspended</span>
                                            @else
                                                <span class="badge bg-success">Active</span>
                                            @endif
                                        </td>
                                        <td>
                                            <small>
                                                {{ $domain['disk_used'] ?: '0' }} MB
                                                @if($domain['disk_limit'])
                                                    / {{ $domain['disk_limit'] }} MB
                                                @endif
                                            </small>
                                        </td>
                                        <td>
                                            <small>{{ $domain['email'] ?: 'N/A' }}</small>
                                        </td>
                                        <td>
                                            <small>{{ $domain['ip'] ?: 'N/A' }}</small>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3 d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="ti ti-info-circle me-1"></i>
                                Total domains: <span id="total-domains">{{ $cPanelDomains->count() }}</span>
                                <span id="filtered-info" style="display: none;"> | Showing: <span id="visible-domains"></span></span>
                            </small>
                            
                            @if($cPanelDomains->count() > 10)
                            <small class="text-muted">
                                <i class="ti ti-search me-1"></i>
                                Use search to filter domains
                            </small>
                            @endif
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

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    @if($server->control_panel === 'cpanel' && $server->hasToken())
                        <button type="button" class="btn btn-outline-primary mb-3 w-100" id="test-connection-btn">
                            <i class="ti ti-world me-1"></i> Test API Connection
                        </button>
                    @elseif($server->control_panel === 'cpanel')
                        <div class="alert alert-warning mb-3">
                            <small><i class="ti ti-alert-triangle me-1"></i> Configure token to test connection</small>
                        </div>
                    @else
                        <div class="alert alert-info mb-3">
                            <small><i class="ti ti-info-circle me-1"></i> Connection test available for cPanel servers only</small>
                        </div>
                    @endif
                    
                    @if($server->control_panel === 'cpanel' && $server->hasToken())
                        <button type="button" class="btn btn-outline-success mb-3 w-100" id="sync-domains-action-btn">
                            <i class="ti ti-refresh me-1"></i> Sync cPanel Domains
                        </button>
                    @endif
                    
                    <form action="{{ route('server.destroy', $server->id) }}" method="POST"
                          onsubmit="return confirm('Are you sure you want to delete this server?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="ti ti-trash me-1"></i> Delete Server
                        </button>
                    </form>
                </div>
            </div>

            @if($server->domains()->count() > 0)
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Domains</h5>
                </div>
                <div class="card-body">
                    <p>This server has {{ $server->domains()->count() }} domain(s):</p>
                    <ul class="list-group">
                        @foreach($server->domains()->take(5)->get() as $domain)
                            <li class="list-group-item">
                                <a href="{{ route('domain.show', $domain->id) }}">{{ $domain->domain }}</a>
                            </li>
                        @endforeach
                        
                        @if($server->domains()->count() > 5)
                            <li class="list-group-item text-center">
                                <em>And {{ $server->domains()->count() - 5 }} more...</em>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sync Domains functionality (both buttons)
    const syncDomainsBtn = document.getElementById('sync-domains-btn');
    const syncDomainsActionBtn = document.getElementById('sync-domains-action-btn');
    
    function handleSyncDomains(btn) {
        const originalText = btn.innerHTML;
        
        // Set loading state
        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i> Syncing...';
        
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
    
    if (syncDomainsBtn) {
        syncDomainsBtn.addEventListener('click', function() {
            handleSyncDomains(this);
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
            btn.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i> Testing...';
            
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
                
                if (domain.includes(searchTerm) || user.includes(searchTerm) || plan.includes(searchTerm)) {
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