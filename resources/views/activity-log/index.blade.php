@extends('layouts/layoutMaster')

@section('title', __('Activity Log'))

@section('vendor-style')
    <link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
@endsection

@section('vendor-script')
    <script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
@endsection

@section('page-script')
    <script src="{{asset('assets/js/laravel-user-management.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Activity Log') }}</h4>
        <p class="text-muted">{{ __('Track all user activities in the system') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <button type="button" class="btn btn-outline-info" onclick="loadActivityStats()">
            <i class="ti ti-chart-line me-1"></i>{{ __('Statistics') }}
        </button>
        <button type="button" class="btn btn-outline-primary" onclick="$('#activitylog-table').DataTable().ajax.reload()">
            <i class="ti ti-refresh me-1"></i>{{ __('Refresh') }}
        </button>
    </div>
</div>

<!-- Activity Statistics Card -->
<div class="row mb-4" id="activity-stats" style="display: none;">
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="avatar mx-auto mb-2">
                    <span class="avatar-initial rounded-circle bg-label-info">
                        <i class="ti ti-calendar-event ti-sm"></i>
                    </span>
                </div>
                <span class="d-block mb-1 text-nowrap">{{ __('Today') }}</span>
                <h2 class="mb-0" id="today-count">0</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="avatar mx-auto mb-2">
                    <span class="avatar-initial rounded-circle bg-label-success">
                        <i class="ti ti-calendar-week ti-sm"></i>
                    </span>
                </div>
                <span class="d-block mb-1 text-nowrap">{{ __('This Week') }}</span>
                <h2 class="mb-0" id="week-count">0</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="avatar mx-auto mb-2">
                    <span class="avatar-initial rounded-circle bg-label-warning">
                        <i class="ti ti-calendar-month ti-sm"></i>
                    </span>
                </div>
                <span class="d-block mb-1 text-nowrap">{{ __('This Month') }}</span>
                <h2 class="mb-0" id="month-count">0</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="avatar mx-auto mb-2">
                    <span class="avatar-initial rounded-circle bg-label-primary">
                        <i class="ti ti-users ti-sm"></i>
                    </span>
                </div>
                <span class="d-block mb-1 text-nowrap">{{ __('Active Users') }}</span>
                <h2 class="mb-0" id="active-users-count">0</h2>
            </div>
        </div>
    </div>
</div>

<!-- Activity Log Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">{{ __('Activity Log') }}</h5>
        <div class="card-header-elements">
            <span class="badge bg-label-info" id="total-activities">{{ __('Loading...') }}</span>
        </div>
    </div>
    <div class="card-datatable table-responsive">
        {!! $dataTable->table(['class' => 'table table-striped table-hover']) !!}
    </div>
</div>

<script>
{!! $dataTable->scripts() !!}

function loadActivityStats() {
    const statsDiv = document.getElementById('activity-stats');
    
    if (statsDiv.style.display === 'none') {
        statsDiv.style.display = 'block';
        
        // Load statistics
        fetch('{{ route("activity-log.statistics") }}')
            .then(response => response.json())
            .then(data => {
                document.getElementById('today-count').textContent = data.today;
                document.getElementById('week-count').textContent = data.week;
                document.getElementById('month-count').textContent = data.month;
                document.getElementById('active-users-count').textContent = data.most_active_users.length;
            })
            .catch(error => {
                console.error('Error loading statistics:', error);
            });
    } else {
        statsDiv.style.display = 'none';
    }
}

// Update total activities count when table loads
$(document).ready(function() {
    $('#activitylog-table').on('draw.dt', function() {
        var info = $('#activitylog-table').DataTable().page.info();
        $('#total-activities').text(info.recordsTotal + ' {{ __("activities") }}');
    });
});
</script>
@endsection 