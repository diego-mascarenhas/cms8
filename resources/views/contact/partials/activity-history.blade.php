@php
    $interactionList = isset($interactionLimit)
        ? $data->contactInteractions->take((int) $interactionLimit)
        : $data->contactInteractions;
@endphp
<h6 class="mb-3">{{ __('History') }}</h6>
<ul class="timeline mb-0 ms-1">
    @forelse ($interactionList as $interaction)
        <li class="timeline-item timeline-item-transparent pb-3">
            <span class="timeline-point timeline-point-primary"></span>
            <div class="timeline-event">
                <div class="timeline-header mb-1">
                    <h6 class="mb-0">{{ $interaction->type->label() }} @if($interaction->subject) — {{ $interaction->subject }} @endif</h6>
                    <small class="text-muted">{{ $interaction->occurred_at->isoFormat('D MMM YYYY, HH:mm') }}
                        @if ($interaction->user)
                            — {{ $interaction->user->name }}
                        @endif
                    </small>
                </div>
                @if ($interaction->body)
                    <p class="mb-0 text-body-secondary">{{ $interaction->body }}</p>
                @endif
            </div>
        </li>
    @empty
        <li class="text-muted">{{ __('No interactions yet.') }}</li>
    @endforelse
</ul>
