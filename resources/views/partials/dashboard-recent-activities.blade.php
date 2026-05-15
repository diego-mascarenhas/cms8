<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between py-3 flex-wrap gap-2">
        <div class="card-title mb-0">
            <h5 class="mb-0">
                <i class="ti ti-history ti-xs me-1"></i>{{ __('Recent contact activity') }}
            </h5>
            <small class="text-muted">{{ __('app.dashboard_contacts_summary_subtitle') }}</small>
        </div>
        @can('contact.list')
            <a href="{{ route('contact-list') }}" class="btn btn-sm btn-label-primary">
                <i class="ti ti-list ti-xs me-1"></i>{{ __('app.dashboard_contacts_view_list') }}
            </a>
        @endcan
    </div>
    <div class="card-body pt-2">
        <div class="mb-4 pb-3 border-bottom">
            <small class="text-muted d-block mb-2">{{ __('app.dashboard_contacts_chart_subtitle') }}</small>
            <div id="dashboardContactsTrendChart" style="min-height: 120px;"></div>
        </div>

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
