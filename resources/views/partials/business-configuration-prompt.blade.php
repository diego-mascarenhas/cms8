@php
    $businessCfgTeam = $team ?? auth()->user()->currentTeam ?? auth()->user()->teams->first();
@endphp
@if($businessCfgTeam && auth()->user()->can('update', $businessCfgTeam) && ! $businessCfgTeam->hasCompletedBusinessConfiguration())
    <div class="alert alert-primary d-flex align-items-start mb-3" role="alert">
        <i class="ti ti-building-store ti-md me-2 mt-1 flex-shrink-0"></i>
        <div class="flex-grow-1">
            <div class="fw-semibold mb-1">{{ __('Complete your business configuration') }}</div>
            <p class="small mb-2">{{ __('Add your business details in a few steps to get more out of Humano.') }}</p>
            <a href="{{ route('team-settings.business-config', $businessCfgTeam) }}" class="btn btn-sm btn-primary">
                {{ __('Configure business') }}
            </a>
        </div>
    </div>
@endif
