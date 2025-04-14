@extends('layouts/contentNavbarLayout')

@section('title', 'Domains')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Domains Management</h4>
        <p class="text-muted">Manage website domains across your servers</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('domain.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>
            Add New Domain
        </a>
    </div>
</div>

<!-- Server Status Summary -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Total Domains</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ \App\Models\Domain::count() }}</h3>
                        </div>
                        <p class="mb-0">Active Domains</p>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="ti ti-world ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Active</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ \App\Models\Domain::where('suspended', false)->count() }}</h3>
                            <p class="text-success mb-0">({{ \App\Models\Domain::count() > 0 ? round((\App\Models\Domain::where('suspended', false)->count() / \App\Models\Domain::count()) * 100) : 0 }}%)</p>
                        </div>
                        <p class="mb-0">Active Domains</p>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="ti ti-check ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>SSL Valid</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ \App\Models\Domain::whereNotNull('data->ssl_status->valid')->where('data->ssl_status->valid', true)->count() }}</h3>
                            <p class="text-primary mb-0">({{ \App\Models\Domain::count() > 0 ? round((\App\Models\Domain::whereNotNull('data->ssl_status->valid')->where('data->ssl_status->valid', true)->count() / \App\Models\Domain::count()) * 100) : 0 }}%)</p>
                        </div>
                        <p class="mb-0">Valid Certificates</p>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="ti ti-shield-check ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Suspended</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ \App\Models\Domain::where('suspended', true)->count() }}</h3>
                            <p class="text-danger mb-0">({{ \App\Models\Domain::count() > 0 ? round((\App\Models\Domain::where('suspended', true)->count() / \App\Models\Domain::count()) * 100) : 0 }}%)</p>
                        </div>
                        <p class="mb-0">Suspended Domains</p>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-danger">
                            <i class="ti ti-ban ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Domains Table -->
<div class="card">
    <div class="card-body">
        {{ $dataTable->table() }}
    </div>
</div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush

@section('vendor-script')
<script src="{{asset('vendors/data-tables/js/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendors/data-tables/extensions/responsive/js/dataTables.responsive.min.js')}}"></script>
<script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>
@endsection 