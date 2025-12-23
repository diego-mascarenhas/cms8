<div wire:poll.5s>
    <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">{{ __('Delivery Stats') }}</h5>
            <div class="d-flex align-items-center">
                <small class="text-muted me-2">{{ __('last updated') }}: {{ now()->format('H:i:s') }}</small>
                <div wire:loading.delay>
                    <span class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">{{ __('Loading...') }}</span>
                    </span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <!-- Total Subscribers -->
                <div class="col-md-4">
                    <div class="d-flex align-items-center stat-filter" style="cursor: pointer;" onclick="filterDeliveriesByStatus('all')">
                        <div class="avatar avatar-sm me-3">
                            <span class="avatar-initial bg-label-primary rounded">
                                <i class="ti ti-users"></i>
                            </span>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $stats->subscribers ?? 0 }}</h6>
                            <small class="text-muted">{{ __('Subscribers') }}</small>
                        </div>
                    </div>
                </div>

                <!-- Sent -->
                <div class="col-md-4">
                    <div class="d-flex align-items-center stat-filter" style="cursor: pointer;" onclick="filterDeliveriesByStatus('sent')">
                        <div class="avatar avatar-sm me-3">
                            <span class="avatar-initial bg-label-success rounded">
                                <i class="ti ti-send"></i>
                            </span>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $stats->sent ?? 0 }}</h6>
                            <small class="text-muted">{{ __('Sent') }}</small>
                        </div>
                    </div>
                </div>

                <!-- Delivered -->
                <div class="col-md-4">
                    <div class="d-flex align-items-center stat-filter" style="cursor: pointer;" onclick="filterDeliveriesByStatus('delivered')">
                        <div class="avatar avatar-sm me-3">
                            <span class="avatar-initial bg-label-info rounded">
                                <i class="ti ti-check"></i>
                            </span>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $stats->delivered ?? 0 }}</h6>
                            <small class="text-muted">{{ __('Delivered') }}</small>
                        </div>
                    </div>
                </div>

                <!-- Opened -->
                <div class="col-md-4">
                    <div class="d-flex align-items-center stat-filter" style="cursor: pointer;" onclick="filterDeliveriesByStatus('opened')">
                        <div class="avatar avatar-sm me-3">
                            <span class="avatar-initial bg-label-warning rounded">
                                <i class="ti ti-eye"></i>
                            </span>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $stats->opened ?? 0 }}</h6>
                            <small class="text-muted">{{ __('Opened') }}</small>
                        </div>
                    </div>
                </div>

                <!-- Clicks -->
                <div class="col-md-4">
                    <div class="d-flex align-items-center stat-filter" style="cursor: pointer;" onclick="filterDeliveriesByStatus('clicked')">
                        <div class="avatar avatar-sm me-3">
                            <span class="avatar-initial bg-label-secondary rounded">
                                <i class="ti ti-mouse"></i>
                            </span>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $stats->clicks ?? 0 }}</h6>
                            <small class="text-muted">{{ __('Clicks') }}</small>
                        </div>
                    </div>
                </div>

                <!-- Failed -->
                <div class="col-md-4">
                    <div class="d-flex align-items-center stat-filter" style="cursor: pointer;" onclick="filterDeliveriesByStatus('failed')">
                        <div class="avatar avatar-sm me-3">
                            <span class="avatar-initial bg-label-danger rounded">
                                <i class="ti ti-x"></i>
                            </span>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $stats->failed ?? 0 }}</h6>
                            <small class="text-muted">{{ __('Failed') }}</small>
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
                        <small class="text-muted">{{ __('Campaign Progress') }}</small>
                        <small class="fw-medium">{{ $stats->ratio ?? 0 }}% {{ __('Open Rate') }}</small>
                    </div>
                    <div class="progress mb-2" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: {{ $sentPercent }}%"></div>
                        <div class="progress-bar bg-info" style="width: {{ max(0, $deliveredPercent - $sentPercent) }}%"></div>
                        <div class="progress-bar bg-warning" style="width: {{ max(0, $openedPercent - $deliveredPercent) }}%"></div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-success">{{ $sentPercent }}% {{ __('Sent') }}</small>
                        <small class="text-info">{{ $deliveredPercent }}% {{ __('Delivered') }}</small>
                        <small class="text-warning">{{ $openedPercent }}% {{ __('Opened') }}</small>
                    </div>
                </div>
            @endif

            <!-- SMTP Status Indicator -->
            @if($isUsingSystemSmtp)
                <div class="mt-4 p-3 bg-light border-start border-primary border-4 rounded-end">
                    <div class="d-flex align-items-center">
                        <i class="ti ti-info-circle text-primary me-2"></i>
                        <div>
                            <small class="fw-medium text-primary">Powered by REVISION ALPHA Emailer</small>
                            <br>
                            <small class="text-muted">
                                Email Marketing fácil, rápido y seguro -
                                <a href="https://revisionalpha.com/emailer" class="text-decoration-none" target="_blank">¡Empieza ahora!</a>
                            </small>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

    <!-- Subscribers Modal -->
    @if($showSubscribersModal)
    <div class="modal fade show" style="display: block; background-color: rgba(0,0,0,0.5);" tabindex="-1" wire:click.self="closeSubscribersModal">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ti ti-users me-2"></i>{{ __('Potential Subscribers') }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeSubscribersModal"></button>
                </div>
                <div class="modal-body">
                    <!-- Stats Summary -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-label-primary">
                                <div class="card-body text-center">
                                    <h3 class="mb-0">{{ $subscribersStats['total'] }}</h3>
                                    <small>{{ __('Total Contacts') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-label-success">
                                <div class="card-body text-center">
                                    <h3 class="mb-0">{{ $subscribersStats['with_delivery'] }}</h3>
                                    <small>{{ __('With Delivery') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-label-warning">
                                <div class="card-body text-center">
                                    <h3 class="mb-0">{{ $subscribersStats['without_delivery'] }}</h3>
                                    <small>{{ __('Pending') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contacts List -->
                    @if(count($potentialSubscribers) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th class="text-center">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($potentialSubscribers as $contact)
                                <tr>
                                    <td>
                                        <a href="{{ route('contact.show', $contact['id']) }}" target="_blank" class="text-body">
                                            {{ $contact['name'] }}
                                        </a>
                                    </td>
                                    <td>{{ $contact['email'] }}</td>
                                    <td class="text-center">
                                        @if($contact['has_delivery'])
                                            <span class="badge bg-success">
                                                <i class="ti ti-check ti-xs"></i> {{ __('Scheduled') }}
                                            </span>
                                        @else
                                            <span class="badge bg-warning">
                                                <i class="ti ti-clock ti-xs"></i> {{ __('Pending') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-5">
                        <i class="ti ti-users-off" style="font-size: 3rem; color: #ccc;"></i>
                        <p class="text-muted mt-3">{{ __('No contacts match the message criteria') }}</p>
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeSubscribersModal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <script>
        function filterDeliveriesByStatus(status) {
            // Use Livewire 3 global event dispatch
            Livewire.dispatch('filterByStatus', { status: status });
        }
    </script>
</div>
