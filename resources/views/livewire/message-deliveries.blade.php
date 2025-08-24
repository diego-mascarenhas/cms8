<div wire:poll.3s>
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">{{ __('Deliveries') }}</h5>
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
                            <th>{{ __('Contact') }}</th>
                            <th>{{ __('Delivery Status') }}</th>
                            <th class="text-center">{{ __('Status') }}</th>
                            <th class="text-center">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deliveries as $delivery)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        @if($delivery['contact_id'])
                                            <a href="{{ route('contact.show', $delivery['contact_id']) }}" class="text-decoration-none">
                                                <h6 class="mb-0 text-primary">{{ $delivery['contact_name'] }}</h6>
                                            </a>
                                        @else
                                            <h6 class="mb-0">{{ $delivery['contact_name'] }}</h6>
                                        @endif
                                        <small class="text-muted">{{ $delivery['contact_email'] }}</small>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        @if($delivery['delivered_at'])
                                            <small class="text-success">
                                                <i class="ti ti-check me-1"></i>{{ __('Delivered') }}: {{ $delivery['delivered_at'] }}
                                            </small>
                                            <small class="text-muted">{{ __('Sent') }}: {{ $delivery['sent_at'] }}</small>
                                        @elseif($delivery['sent_at'])
                                            @if($delivery['status_text'] === 'Scheduled')
                                                <small class="text-warning">
                                                    <i class="ti ti-clock me-1"></i>{{ __('Scheduled') }}: {{ $delivery['sent_at'] }}
                                                </small>
                                            @else
                                                <small class="text-primary">
                                                    <i class="ti ti-send me-1"></i>{{ __('Sent') }}: {{ $delivery['sent_at'] }}
                                                </small>
                                            @endif
                                        @else
                                            <span class="text-muted">{{ __('Pending') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <span class="badge bg-label-{{ $delivery['status'] }}">
                                            {{ $delivery['status_text'] }}
                                        </span>

                                        {{-- Opened Icon --}}
                                        @if($delivery['has_opened'])
                                            <i class="ti ti-eye ti-sm text-info"
                                               data-bs-toggle="tooltip"
                                               data-bs-placement="top"
                                               data-bs-original-title="{{ __('Opened') }}: {{ $delivery['opened_at'] }}"
                                               style="cursor: help;"></i>
                                        @endif

                                        {{-- Clicked Icon --}}
                                        @if($delivery['has_clicked'])
                                            <i class="ti ti-mouse ti-sm text-success"
                                               data-bs-toggle="tooltip"
                                               data-bs-placement="top"
                                               data-bs-original-title="{{ __('Clicked') }}: {{ $delivery['clicked_at'] }}"
                                               style="cursor: help;"></i>
                                        @endif
                                    </div>
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
                    <p class="text-muted mt-2">{{ __('No deliveries yet') }}</p>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Initialize tooltips after Livewire updates --}}
<script>
// Function to initialize tooltips
function initializeTooltips() {
    // Dispose existing tooltips to prevent duplicates
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        var existingTooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
        if (existingTooltip) {
            existingTooltip.dispose();
        }
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Initialize tooltips after Livewire updates
document.addEventListener('livewire:updated', function() {
    setTimeout(initializeTooltips, 100); // Small delay to ensure DOM is ready
});

// Initialize tooltips on first load
document.addEventListener('DOMContentLoaded', function() {
    initializeTooltips();
});

// Also initialize when the component is loaded
window.addEventListener('load', function() {
    initializeTooltips();
});
</script>
