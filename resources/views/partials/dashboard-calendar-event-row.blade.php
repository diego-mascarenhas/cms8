<tr>
    <td>
        <div class="d-flex flex-column">
            <span class="fw-medium">{{ $event['title'] }}</span>
            @if (! empty($event['guests']))
                <small class="text-muted">{{ implode(', ', $event['guests']) }}</small>
            @endif
            @if (! empty($event['location']))
                <small class="text-muted"><i class="ti ti-map-pin ti-xs me-1"></i>{{ $event['location'] }}</small>
            @endif
        </div>
    </td>
    @if ($showDate ?? false)
        <td class="text-nowrap text-muted small">{{ $event['date_display'] }}</td>
    @endif
    <td class="text-nowrap text-center small text-muted">{{ $event['time_display'] }}</td>
    <td class="text-center">
        <span class="badge rounded-pill bg-label-{{ $event['label_class'] }}">{{ __($event['label']) }}</span>
    </td>
</tr>
