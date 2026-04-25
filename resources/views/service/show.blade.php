@extends('layouts/layoutMaster')

@section('title', __('Service Details'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-user-view.css') }}" />
<style>
    .tab-content {
        padding: 0 !important;
        background: transparent !important;
    }
    .json-data-container {
        max-height: 500px;
        overflow-y: auto;
    }
</style>
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('page-script')
<script src="{{ asset('assets/js/app-user-view.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Service') }}/</span>
            {{ $service->description ?: __('Service') . ' #' . $service->id }}
        </h4>
        <p class="text-muted">
            Created on {{ \Carbon\Carbon::parse($service->created_at)->format('F d, Y') }}
        </p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @can('service.edit')
        <a href="{{ route('service.edit', $service->id) }}" class="btn btn-primary waves-effect waves-light">
            <i class="ti ti-edit me-1"></i>{{ __('Edit Service') }}
        </a>
        @endcan
        @if($service->client && $service->client->responsible_id)
        <a href="#" class="btn btn-outline-primary waves-effect waves-light">
            <i class="ti ti-user me-1"></i>View Responsible
        </a>
        @endif
    </div>
</div>

<div class="row">
    <!-- Service Sidebar -->
    <div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
        <!-- Service Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="user-avatar-section">
                    <div class="d-flex align-items-center flex-column">
                        <img class="img-fluid rounded mb-3 pt-1 mt-4"
                            src="{{ asset('assets/img/icons/brands/social-label.png') }}" height="100"
                            width="100" alt="Service icon" />
                        <div class="user-info text-center">
                            <h4 class="mb-2">{{ $service->description ?: 'Service #' . $service->id }}</h4>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-start flex-wrap mt-3 pt-3 pb-4 border-bottom">
                    <div class="d-flex align-items-start me-4 mt-3 gap-2">
                        <span class="badge bg-label-primary p-2 rounded">
                            <i class='ti ti-calendar ti-sm'></i>
                        </span>
                        <div>
                            <p class="mb-0 fw-medium" style="line-height: 1.2;">
                                {{ \Carbon\Carbon::parse($service->updated_at)->format('d/m/Y') }}
                            </p>
                            <small style="line-height: 1.2;">Last updated</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-start mt-3 gap-2">
                        <span class="badge {{ $status['class'] }} p-2 rounded">
                            <i class='ti ti-activity ti-sm'></i>
                        </span>
                        <div>
                            <p class="mb-0 fw-medium" style="line-height: 1.2;">{{ $status['label'] }}</p>
                            <small style="line-height: 1.2;">Status</small>
                        </div>
                    </div>
                </div>
                <div class="mt-4 info-container">
                    <h5 class="mb-3">{{ __('Service Details') }}</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2 pt-1">
                            <span class="fw-medium me-1">{{ __('Client') }}:</span>
                            <span>{{ $service->client ? $service->client->name : __('Not assigned') }}</span>
                        </li>
                        @if($service->client && $service->client->responsible_id)
                        <li class="mb-2 pt-1">
                            <span class="fw-medium me-1">{{ __('Responsible') }}:</span>
                            <span>{{ $service->client->responsible ? $service->client->responsible->name : __('User') . ' #' . $service->client->responsible_id }}</span>
                        </li>
                        @endif
                        <li class="mb-2 pt-1">
                            <span class="fw-medium me-1">{{ __('Category') }}:</span>
                            <span>{{ $service->category ? $service->category->name : __('Not assigned') }}</span>
                        </li>
                        <li class="mb-2 pt-1">
                            <span class="fw-medium me-1">{{ __('Operation') }}:</span>
                            <span>{{ $service->operation }}</span>
                        </li>
                        <li class="mb-2 pt-1">
                            <span class="fw-medium me-1">{{ __('Price') }}:</span>
                            <span>{{ $service->price ? '$' . number_format($service->price, 2) : __('Not set') }}</span>
                        </li>
                        @if($service->discount)
                        <li class="mb-2 pt-1">
                            <span class="fw-medium me-1">{{ __('Discount') }}:</span>
                            <span>{{ $service->discount }}%</span>
                        </li>
                        @endif
                        <li class="mb-2 pt-1">
                            <span class="fw-medium me-1">{{ __('Frequency') }}:</span>
                            <span>{{ $service->frequency ? $service->frequency . ' ' . __('months') : __('Not set') }}</span>
                        </li>
                        @if($service->next_billing)
                        <li class="mb-2 pt-1">
                            <span class="fw-medium me-1">{{ __('Next Billing') }}:</span>
                            <span>{{ \Carbon\Carbon::parse($service->next_billing)->format('d/m/Y') }}</span>
                        </li>
                        @endif
                        @if($service->last_billed)
                        <li class="mb-2 pt-1">
                            <span class="fw-medium me-1">{{ __('Last Billed') }}:</span>
                            <span>{{ \Carbon\Carbon::parse($service->last_billed)->format('d/m/Y') }}</span>
                        </li>
                        @endif
                        @if($service->expires_at)
                        <li class="mb-2 pt-1">
                            <span class="fw-medium me-1">{{ __('Expires At') }}:</span>
                            <span>{{ \Carbon\Carbon::parse($service->expires_at)->format('d/m/Y') }}</span>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Content -->
    <div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
        <!-- Service Tabs -->
        <div class="card mb-4">
            <div class="card-body">
                <ul class="nav nav-pills flex-column flex-md-row mb-4">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#service-overview">
                            <i class="ti ti-user-check ti-xs me-1"></i>{{ __('Overview') }}
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#service-data">
                            <i class="ti ti-file-text ti-xs me-1"></i>{{ __('Service Data') }}
                        </button>
                    </li>
                </ul>
                <div class="tab-content">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="service-overview">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Description</h5>
                                <p>{{ $service->description ?? 'No description available' }}</p>

                                @if(!empty($serviceData))
                                <div class="mt-4">
                                    <h5>{{ __('Metadata') }}</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            @foreach($serviceData as $key => $value)
                                            <tr>
                                                <th style="width: 30%">{{ \Illuminate\Support\Str::headline((string) $key) }}</th>
                                                <td>
                                                    @if(is_array($value) || is_object($value))
                                                        <pre class="mb-0">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    @else
                                                        {{ $value }}
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </table>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Service Data Tab -->
                    <div class="tab-pane fade" id="service-data">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">{{ __('Raw Service Data') }}</h5>
                                <div class="json-data-container">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th style="width: 30%">Field</th>
                                                    <th>Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($serviceData as $key => $value)
                                                <tr>
                                                    <td>{{ $key }}</td>
                                                    <td>
                                                        @if(is_array($value) || is_object($value))
                                                            <pre>{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
                                                        @else
                                                            {{ $value }}
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
