@extends('layouts/layoutMaster')

@section('title', 'Email Plans Management')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<style>
.plan-card {
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}
.plan-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.plan-card.active {
    border-color: #696cff;
    background: linear-gradient(135deg, #f8f7ff 0%, #ffffff 100%);
}
.usage-bar {
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}
.usage-progress {
    height: 100%;
    transition: width 0.5s ease;
    border-radius: 4px;
}
.team-stats {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    padding: 20px;
}
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">📧 Email Plans Management</h4>
        <p class="text-muted">Manage email plans and limits for all teams</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <button type="button" class="btn btn-info" onclick="refreshAllData()">
            <i class="ti ti-refresh me-1"></i>Refresh Data
        </button>
        <button type="button" class="btn btn-success" onclick="syncAllUsage()">
            <i class="ti ti-database me-1"></i>Sync All Usage
        </button>
    </div>
</div>

<!-- Plans Overview -->
<div class="row mb-4">
    @foreach($availablePlans as $planData)
    <div class="col-md-4">
        <div class="card plan-card mb-3" data-plan="{{ $planData['value'] }}">
            <div class="card-body text-center">
                <div class="mb-3">
                    @if($planData['value'] === 'basic')
                        <i class="ti ti-mail text-primary" style="font-size: 3rem;"></i>
                    @elseif($planData['value'] === 'foundation')
                        <i class="ti ti-mail-forward text-info" style="font-size: 3rem;"></i>
                    @else
                        <i class="ti ti-mail-bolt text-success" style="font-size: 3rem;"></i>
                    @endif
                </div>
                <h5 class="card-title">{{ $planData['name'] }}</h5>
                <p class="text-muted">{{ $planData['description'] }}</p>

                <div class="row text-center">
                    <div class="col-4">
                        <div class="fw-semibold">Monthly</div>
                        <small class="text-muted">{{ number_format($planData['config']['monthly_limit']) }}</small>
                    </div>
                    <div class="col-4">
                        <div class="fw-semibold">Daily</div>
                        <small class="text-muted">
                            {{ $planData['config']['daily_limit'] ? number_format($planData['config']['daily_limit']) : '∞' }}
                        </small>
                    </div>
                    <div class="col-4">
                        <div class="fw-semibold">Contacts</div>
                        <small class="text-muted">{{ number_format($planData['config']['contact_limit']) }}</small>
                    </div>
                </div>

                <div class="mt-3">
                    @php
                        $teamsWithPlan = $teams->filter(fn($team) => $team['plan']->value === $planData['value']);
                    @endphp
                    <span class="badge bg-label-primary">{{ $teamsWithPlan->count() }} teams</span>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<!-- Teams Management -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Teams Email Plans</h5>
            <div class="d-flex gap-2">
                <select id="planFilter" class="form-select form-select-sm" onchange="filterByPlan(this.value)">
                    <option value="">All Plans</option>
                    @foreach($availablePlans as $planData)
                        <option value="{{ $planData['value'] }}">{{ $planData['name'] }}</option>
                    @endforeach
                </select>
                <select id="statusFilter" class="form-select form-select-sm" onchange="filterByStatus(this.value)">
                    <option value="">All Status</option>
                    <option value="ok">Within Limits</option>
                    <option value="warning">Near Limits</option>
                    <option value="over">Over Limits</option>
                </select>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row" id="teamsContainer">
            @foreach($teams as $team)
            <div class="col-lg-6 mb-4 team-item"
                 data-plan="{{ $team['plan']->value }}"
                 data-status="{{ $team['limits']['over_monthly'] || $team['limits']['over_daily'] || $team['limits']['over_contacts'] ? 'over' : ($team['remaining']['monthly_remaining'] < ($team['remaining']['monthly_limit'] * 0.2) ? 'warning' : 'ok') }}">

                <div class="card team-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h6 class="mb-1">{{ $team['name'] }}</h6>
                                <span class="badge bg-label-{{ $team['plan']->value === 'basic' ? 'primary' : ($team['plan']->value === 'foundation' ? 'info' : 'success') }}">
                                    {{ $team['plan']->getDisplayName() }}
                                </span>
                            </div>
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" data-bs-toggle="dropdown">
                                    <i class="ti ti-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><h6 class="dropdown-header">Change Plan</h6></li>
                                    @foreach($availablePlans as $planData)
                                        @if($planData['value'] !== $team['plan']->value)
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="assignPlan({{ $team['id'] }}, '{{ $planData['value'] }}', '{{ $planData['name'] }}')">
                                                <i class="ti ti-arrow-right me-1"></i>{{ $planData['name'] }}
                                            </a>
                                        </li>
                                        @endif
                                    @endforeach
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="viewTeamDetails({{ $team['id'] }})">
                                            <i class="ti ti-eye me-1"></i>View Details
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="syncTeamUsage({{ $team['id'] }})">
                                            <i class="ti ti-refresh me-1"></i>Sync Usage
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Monthly Usage -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Monthly Usage</small>
                                <small class="fw-semibold">{{ number_format($team['remaining']['monthly_used']) }} / {{ number_format($team['remaining']['monthly_limit']) }}</small>
                            </div>
                            <div class="usage-bar bg-light">
                                @php
                                    $monthlyPercent = $team['remaining']['monthly_limit'] > 0 ? ($team['remaining']['monthly_used'] / $team['remaining']['monthly_limit']) * 100 : 0;
                                    $monthlyColor = $monthlyPercent >= 100 ? 'danger' : ($monthlyPercent >= 80 ? 'warning' : 'success');
                                @endphp
                                <div class="usage-progress bg-{{ $monthlyColor }}" style="width: {{ min(100, $monthlyPercent) }}%"></div>
                            </div>
                        </div>

                        <!-- Daily Usage -->
                        @if($team['remaining']['daily_limit'])
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Daily Usage</small>
                                <small class="fw-semibold">{{ number_format($team['remaining']['daily_used']) }} / {{ number_format($team['remaining']['daily_limit']) }}</small>
                            </div>
                            <div class="usage-bar bg-light">
                                @php
                                    $dailyPercent = $team['remaining']['daily_limit'] > 0 ? ($team['remaining']['daily_used'] / $team['remaining']['daily_limit']) * 100 : 0;
                                    $dailyColor = $dailyPercent >= 100 ? 'danger' : ($dailyPercent >= 80 ? 'warning' : 'success');
                                @endphp
                                <div class="usage-progress bg-{{ $dailyColor }}" style="width: {{ min(100, $dailyPercent) }}%"></div>
                            </div>
                        </div>
                        @endif

                        <!-- Contacts -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Contacts</small>
                                <small class="fw-semibold">{{ number_format($team['contacts_count']) }} / {{ number_format($team['plan_config']['contact_limit']) }}</small>
                            </div>
                            <div class="usage-bar bg-light">
                                @php
                                    $contactsPercent = $team['plan_config']['contact_limit'] > 0 ? ($team['contacts_count'] / $team['plan_config']['contact_limit']) * 100 : 0;
                                    $contactsColor = $contactsPercent >= 100 ? 'danger' : ($contactsPercent >= 80 ? 'warning' : 'success');
                                @endphp
                                <div class="usage-progress bg-{{ $contactsColor }}" style="width: {{ min(100, $contactsPercent) }}%"></div>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                @if($team['limits']['can_send'])
                                    <span class="badge bg-success">✅ Can Send</span>
                                @else
                                    <span class="badge bg-danger">❌ Blocked</span>
                                @endif
                            </div>
                            <small class="text-muted">
                                by {{ $team['assigned_by']?->name ?? 'System' }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
function assignPlan(teamId, planValue, planName) {
    Swal.fire({
        title: 'Assign Email Plan',
        text: `Are you sure you want to assign "${planName}" plan to this team?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, assign it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Assigning Plan...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Make AJAX request
            fetch(`/email-plans-management/${teamId}/assign`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ plan: planValue })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        location.reload(); // Refresh to show changes
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred while assigning the plan', 'error');
            });
        }
    });
}

function syncTeamUsage(teamId) {
    // Show loading
    Swal.fire({
        title: 'Syncing Usage...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch(`/email-plans-management/${teamId}/sync-usage`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Success!',
                text: data.message,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
            setTimeout(() => location.reload(), 2000);
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'An error occurred while syncing usage', 'error');
    });
}

function viewTeamDetails(teamId) {
    fetch(`/email-plans-management/${teamId}/details`)
    .then(response => response.json())
    .then(data => {
        if (data.team) {
            const team = data.team;
            Swal.fire({
                title: `📧 ${team.name}`,
                html: `
                    <div class="text-start">
                        <h6>Current Plan: <span class="badge bg-primary">${team.plan.name}</span></h6>
                        <p class="text-muted">${team.plan.description}</p>

                        <hr>

                        <div class="row">
                            <div class="col-6">
                                <strong>Monthly Emails:</strong><br>
                                <span class="text-${team.status.over_monthly ? 'danger' : 'success'}">
                                    ${team.limits.monthly_used.toLocaleString()} / ${team.limits.monthly_limit.toLocaleString()}
                                </span>
                            </div>
                            <div class="col-6">
                                <strong>Daily Emails:</strong><br>
                                <span class="text-${team.status.over_daily ? 'danger' : 'success'}">
                                    ${team.limits.daily_used.toLocaleString()} / ${team.limits.daily_limit ? team.limits.daily_limit.toLocaleString() : '∞'}
                                </span>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-12">
                                <strong>Contacts:</strong><br>
                                <span class="text-${team.status.over_contacts ? 'danger' : 'success'}">
                                    ${team.contacts_count.toLocaleString()} / ${team.contact_limit.toLocaleString()}
                                </span>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-6">
                                <strong>Actual Usage (DB):</strong><br>
                                Monthly: ${team.actual_usage.monthly_used}<br>
                                Daily: ${team.actual_usage.daily_used}
                            </div>
                            <div class="col-6">
                                <strong>Can Send:</strong><br>
                                <span class="badge bg-${team.status.can_send ? 'success' : 'danger'}">
                                    ${team.status.can_send ? '✅ Yes' : '❌ No'}
                                </span>
                            </div>
                        </div>

                        <hr>

                        <small class="text-muted">
                            Assigned by: ${team.assigned_by}<br>
                            Date: ${team.assigned_at || 'N/A'}
                        </small>
                    </div>
                `,
                width: 600,
                confirmButtonText: 'Close'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'Could not load team details', 'error');
    });
}

function refreshAllData() {
    location.reload();
}

function syncAllUsage() {
    Swal.fire({
        title: 'Sync All Teams Usage?',
        text: 'This will synchronize email usage for all teams with actual database data.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, sync all!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Syncing All Teams...',
                text: 'This may take a few seconds',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Sync each team sequentially
            let completed = 0;
            const teams = @json($teams->pluck('id'));

            teams.forEach((teamId, index) => {
                setTimeout(() => {
                    fetch(`/email-plans-management/${teamId}/sync-usage`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                    .then(() => {
                        completed++;
                        if (completed === teams.length) {
                            Swal.fire({
                                title: 'Complete!',
                                text: `Synced usage for ${completed} teams`,
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => location.reload());
                        }
                    });
                }, index * 500); // Stagger requests by 500ms
            });
        }
    });
}

function filterByPlan(planValue) {
    const teams = document.querySelectorAll('.team-item');
    teams.forEach(team => {
        if (!planValue || team.dataset.plan === planValue) {
            team.style.display = 'block';
        } else {
            team.style.display = 'none';
        }
    });
}

function filterByStatus(statusValue) {
    const teams = document.querySelectorAll('.team-item');
    teams.forEach(team => {
        if (!statusValue || team.dataset.status === statusValue) {
            team.style.display = 'block';
        } else {
            team.style.display = 'none';
        }
    });
}
</script>
@endsection
