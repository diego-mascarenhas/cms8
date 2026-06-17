<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Settings') }}/</span> {{ $title }}
        </h4>
        @if (! empty($subtitle ?? null))
            <p class="text-muted mb-0">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2 align-items-center">
        {!! $actions ?? '' !!}
        <a href="{{ route('team-settings.index', $team) }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back to Settings') }}
        </a>
    </div>
</div>
