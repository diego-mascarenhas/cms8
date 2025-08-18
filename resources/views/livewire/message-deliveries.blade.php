<div wire:poll.3s>
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Deliveries</h5>
            <div class="d-flex align-items-center">
                <small class="text-muted me-2">{{ count($deliveries) }} total</small>
                <div wire:loading.delay>
                    <span class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </span>
                </div>
            </div>
        </div>
        <div class="card-body table-responsive">
            @if(count($deliveries) > 0)
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Contact</th>
                            <th>Delivery Status</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deliveries as $delivery)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <h6 class="mb-0">{{ $delivery['contact_name'] }}</h6>
                                        <small class="text-muted">{{ $delivery['contact_email'] }}</small>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        @if($delivery['delivered_at'])
                                            <small class="text-success">
                                                <i class="ti ti-check me-1"></i>Delivered: {{ $delivery['delivered_at'] }}
                                            </small>
                                            <small class="text-muted">Sent: {{ $delivery['sent_at'] }}</small>
                                        @elseif($delivery['sent_at'])
                                            @if($delivery['status_text'] === 'Scheduled')
                                                <small class="text-warning">
                                                    <i class="ti ti-clock me-1"></i>Scheduled: {{ $delivery['sent_at'] }}
                                                </small>
                                            @else
                                                <small class="text-primary">
                                                    <i class="ti ti-send me-1"></i>Sent: {{ $delivery['sent_at'] }}
                                                </small>
                                            @endif
                                        @else
                                            <span class="text-muted">Pending</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-label-{{ $delivery['status'] }}">
                                        {{ $delivery['status_text'] }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($delivery['status_text'] !== 'Scheduled')
                                        <a href="#" class="text-info" onclick="resendDelivery({{ $delivery['id'] }}, this)" title="Reenviar email">
                                            <i class="ti ti-mail-forward ti-sm"></i>
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="text-center py-4">
                    <i class="ti ti-inbox ti-lg text-muted"></i>
                    <p class="text-muted mt-2">No deliveries yet</p>
                </div>
            @endif
        </div>
    </div>
</div>
