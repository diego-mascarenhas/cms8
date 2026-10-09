@php
    $interactionList = isset($interactionLimit)
        ? $data->contactInteractions->take((int) $interactionLimit)
        : $data->contactInteractions;
    $historyAsCard = $historyAsCard ?? false;
    $summaryOnly = $summaryOnly ?? false;
@endphp
@if ($historyAsCard)
<div class="card mb-4">
    <h5 class="card-header">{{ __('History') }}</h5>
    <div class="card-body">
@else
<h6 class="mb-3">{{ __('History') }}</h6>
@endif
<ul class="timeline mb-0 ms-1">
    @forelse ($interactionList as $interaction)
        <li class="timeline-item timeline-item-transparent pb-3">
            <span class="timeline-point timeline-point-primary"></span>
            <div class="timeline-event">
                <div class="timeline-header mb-1">
                    <h6 class="mb-0">
                        {{ $interaction->type->label() }}
                        @if (! $summaryOnly && $interaction->subject)
                            — {{ $interaction->subject }}
                        @endif
                    </h6>
                    <small class="text-muted">{{ $interaction->occurred_at->isoFormat('D MMM YYYY, HH:mm') }}
                        @if ($interaction->user)
                            — {{ $interaction->user->name }}
                        @endif
                    </small>
                </div>
                @if (! $summaryOnly && filled($interaction->body))
                    <button type="button" class="btn btn-sm btn-link px-0 interaction-body-toggle" data-bs-toggle="collapse" data-bs-target="#interaction-body-{{ $interaction->id }}" aria-expanded="false" aria-controls="interaction-body-{{ $interaction->id }}">
                        <span class="when-closed">{{ __('Details') }}</span>
                        <span class="when-open d-none">{{ __('actions.hide') }}</span>
                    </button>
                    <div class="collapse" id="interaction-body-{{ $interaction->id }}">
                        @php
                            $chatLines = $interaction->chatLines();
                        @endphp
                        @if ($chatLines !== [])
                            <div class="d-flex flex-column gap-2 mt-2">
                                @foreach ($chatLines as $line)
                                    <div>
                                        <div class="d-flex justify-content-between gap-2 small text-muted">
                                            <span class="fw-semibold text-body">{{ $line['author'] }}</span>
                                            <span>{{ $line['at'] }}</span>
                                        </div>
                                        <div class="mb-0">{!! nl2br(e($line['text'])) !!}</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="mb-0 mt-2 text-body-secondary">{!! nl2br(e($interaction->body)) !!}</div>
                        @endif
                    </div>
                @endif
            </div>
        </li>
    @empty
        <li class="text-muted">{{ __('No interactions yet.') }}</li>
    @endforelse
</ul>
@if ($historyAsCard)
    </div>
</div>
@endif
@if (! $summaryOnly)
    @once
        @push('scripts')
            <script>
                document.addEventListener('shown.bs.collapse', function (event) {
                    var button = document.querySelector('.interaction-body-toggle[data-bs-target="#' + event.target.id + '"]');
                    if (!button) {
                        return;
                    }
                    button.querySelector('.when-closed').classList.add('d-none');
                    button.querySelector('.when-open').classList.remove('d-none');
                });
                document.addEventListener('hidden.bs.collapse', function (event) {
                    var button = document.querySelector('.interaction-body-toggle[data-bs-target="#' + event.target.id + '"]');
                    if (!button) {
                        return;
                    }
                    button.querySelector('.when-open').classList.add('d-none');
                    button.querySelector('.when-closed').classList.remove('d-none');
                });
            </script>
        @endpush
    @endonce
@endif
