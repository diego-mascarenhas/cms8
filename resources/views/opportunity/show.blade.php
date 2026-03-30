@extends('layouts/layoutMaster')

@section('title', __('Opportunities'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Opportunities') }}/</span> {{ $opportunity->name }}</h4>
        <p class="text-muted">{{ $opportunity->stage?->name ?? '' }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @can('update', $opportunity)
            <a href="{{ route('opportunity.edit', $opportunity->id) }}" class="btn btn-primary"><i class="ti ti-edit me-1"></i>{{ __('Edit') }}</a>
        @endcan
        <a href="{{ route('contact.show', $opportunity->contact_id) }}" class="btn btn-label-secondary"><i class="ti ti-user me-1"></i>{{ __('Contact') }}</a>
        <a href="{{ route('opportunity.index') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card">
            <h5 class="card-header">{{ __('Details') }}</h5>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ __('Opened') }}</dt>
                    <dd class="col-sm-8">{{ $opportunity->opened_at?->format('Y-m-d') }}</dd>
                    <dt class="col-sm-4">{{ __('Estimated amount') }}</dt>
                    <dd class="col-sm-8">
                        @if ($opportunity->estimated_amount !== null)
                            {{ number_format((float) $opportunity->estimated_amount, 2) }} {{ $opportunity->currency?->code }}
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="col-sm-4">{{ __('Responsible') }}</dt>
                    <dd class="col-sm-8">{{ $opportunity->responsible?->name ?? '—' }}</dd>
                    <dt class="col-sm-4">{{ __('Offering') }}</dt>
                    <dd class="col-sm-8">
                        @if ($opportunity->offering_type && $opportunity->offering)
                            {{ class_basename($opportunity->offering_type) }}: {{ $opportunity->offering->name ?? ('#'.$opportunity->offering_id) }}
                        @else
                            {{ $opportunity->offering_summary ?: '—' }}
                        @endif
                    </dd>
                </dl>
                @if ($opportunity->description)
                    <h6 class="mt-3">{{ __('Description') }}</h6>
                    <p class="mb-0">{{ $opportunity->description }}</p>
                @endif
                @if ($opportunity->notes)
                    <h6 class="mt-3">{{ __('Notes') }}</h6>
                    <p class="mb-0">{{ $opportunity->notes }}</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card">
            <h5 class="card-header">{{ __('This opportunity') }}</h5>
            <div class="card-body">
                <ul class="timeline mb-0 ms-1">
                    @forelse ($interactions as $interaction)
                        <li class="timeline-item timeline-item-transparent pb-3">
                            <span class="timeline-point timeline-point-primary"></span>
                            <div class="timeline-event">
                                <h6 class="mb-0">{{ $interaction->type->name }} @if($interaction->subject) — {{ $interaction->subject }} @endif</h6>
                                <small class="text-muted">{{ $interaction->occurred_at->isoFormat('D MMM YYYY, HH:mm') }}</small>
                                @if ($interaction->body)
                                    <p class="mb-0 small">{{ $interaction->body }}</p>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="text-muted small">{{ __('No interactions linked to this opportunity yet.') }}</li>
                    @endforelse
                </ul>
                <p class="text-muted small mt-2 mb-0">{{ __('Log interactions from the contact Activity tab and optionally link this opportunity.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
