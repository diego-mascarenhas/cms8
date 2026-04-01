<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between py-3">
        <h5 class="card-title mb-0">
            <i class="ti ti-history ti-xs me-1"></i>{{ __('Recent contact activity') }}
        </h5>
    </div>
    <div class="card-body pt-2">
        @forelse ($recentContactActivities as $interaction)
            <div class="d-flex flex-wrap gap-2 border-bottom pb-3 mb-3 align-items-start">
                <div class="flex-grow-1 min-w-0">
                    <a href="{{ route('contact.show', $interaction->contact_id) }}#activity" class="fw-medium text-body d-inline-block text-truncate" style="max-width: 100%;">
                        {{ $interaction->contact->name }} {{ $interaction->contact->surname }}
                    </a>
                    <div class="small text-muted">
                        {{ $interaction->type->label() }}
                        @if ($interaction->subject)
                            — {{ $interaction->subject }}
                        @endif
                    </div>
                    @if ($interaction->body)
                        <p class="mb-0 small text-body-secondary mt-1">{{ Str::limit($interaction->body, 120) }}</p>
                    @endif
                </div>
                <div class="text-md-end small text-muted text-nowrap">
                    {{ $interaction->occurred_at->isoFormat('D MMM YYYY, HH:mm') }}
                    @if ($interaction->user)
                        <div class="small">{{ $interaction->user->name }}</div>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-muted mb-0">{{ __('No recent contact activity.') }}</p>
        @endforelse
    </div>
</div>
