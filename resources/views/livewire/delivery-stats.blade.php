<div wire:poll.5s>
    <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Delivery Stats</h5>
            <div class="d-flex align-items-center">
                <small class="text-muted me-2">last updated: {{ now()->format('H:i:s') }}</small>
                <div wire:loading.delay>
                    <span class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <!-- Total Subscribers -->
                <div class="col-md-4">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-sm me-3">
                            <span class="avatar-initial bg-label-primary rounded">
                                <i class="ti ti-users"></i>
                            </span>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $stats->subscribers ?? 0 }}</h6>
                            <small class="text-muted">Subscribers</small>
                        </div>
                    </div>
                </div>

                <!-- Sent -->
                <div class="col-md-4">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-sm me-3">
                            <span class="avatar-initial bg-label-success rounded">
                                <i class="ti ti-send"></i>
                            </span>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $stats->sent ?? 0 }}</h6>
                            <small class="text-muted">Sent</small>
                        </div>
                    </div>
                </div>

                <!-- Delivered -->
                <div class="col-md-4">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-sm me-3">
                            <span class="avatar-initial bg-label-info rounded">
                                <i class="ti ti-check"></i>
                            </span>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $stats->delivered ?? 0 }}</h6>
                            <small class="text-muted">Delivered</small>
                        </div>
                    </div>
                </div>

                <!-- Opened -->
                <div class="col-md-4">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-sm me-3">
                            <span class="avatar-initial bg-label-warning rounded">
                                <i class="ti ti-eye"></i>
                            </span>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $stats->opened ?? 0 }}</h6>
                            <small class="text-muted">Opened</small>
                        </div>
                    </div>
                </div>

                <!-- Clicks -->
                <div class="col-md-4">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-sm me-3">
                            <span class="avatar-initial bg-label-secondary rounded">
                                <i class="ti ti-mouse"></i>
                            </span>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $stats->clicks ?? 0 }}</h6>
                            <small class="text-muted">Clicks</small>
                        </div>
                    </div>
                </div>

                <!-- Failed -->
                <div class="col-md-4">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-sm me-3">
                            <span class="avatar-initial bg-label-danger rounded">
                                <i class="ti ti-x"></i>
                            </span>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $stats->failed ?? 0 }}</h6>
                            <small class="text-muted">Failed</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Bar -->
            @if(($stats->subscribers ?? 0) > 0)
                @php
                    $sentPercent = round((($stats->sent ?? 0) / $stats->subscribers) * 100, 1);
                    $deliveredPercent = round((($stats->delivered ?? 0) / $stats->subscribers) * 100, 1);
                    $openedPercent = round((($stats->opened ?? 0) / $stats->subscribers) * 100, 1);
                @endphp

                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">Campaign Progress</small>
                        <small class="fw-medium">{{ $stats->ratio ?? 0 }}% Open Rate</small>
                    </div>
                    <div class="progress mb-2" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: {{ $sentPercent }}%"></div>
                        <div class="progress-bar bg-info" style="width: {{ max(0, $deliveredPercent - $sentPercent) }}%"></div>
                        <div class="progress-bar bg-warning" style="width: {{ max(0, $openedPercent - $deliveredPercent) }}%"></div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-success">{{ $sentPercent }}% Sent</small>
                        <small class="text-info">{{ $deliveredPercent }}% Delivered</small>
                        <small class="text-warning">{{ $openedPercent }}% Opened</small>
                    </div>
                </div>
            @endif


        </div>
    </div>
</div>
