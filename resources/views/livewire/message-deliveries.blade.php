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
                            <th>Email</th>
                            <th>Scheduled/Sent At</th>
                            <th>Delivered At</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deliveries as $delivery)
                            <tr>
                                <td>{{ $delivery['contact_name'] }}</td>
                                <td>
                                    <small class="text-muted">{{ $delivery['contact_email'] }}</small>
                                </td>
                                <td>
                                    @if($delivery['delivered_at'])
                                        <small class="text-success">{{ $delivery['sent_at'] }}</small>
                                    @elseif($delivery['sent_at'])
                                        @if($delivery['status_text'] === 'Scheduled')
                                            <small class="text-warning">
                                                <i class="ti ti-clock me-1"></i>{{ $delivery['sent_at'] }}
                                            </small>
                                        @else
                                            <small>{{ $delivery['sent_at'] }}</small>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($delivery['delivered_at'])
                                        <small>{{ $delivery['delivered_at'] }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $delivery['status'] }}">
                                        {{ $delivery['status_text'] }}
                                    </span>
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
