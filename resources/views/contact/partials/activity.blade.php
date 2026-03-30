@php
    $interactionTypes = \App\Enums\ContactInteractionType::cases();
@endphp
<div class="card mb-4">
    <h5 class="card-header d-flex justify-content-between align-items-center">
        <span>{{ __('Activity') }}</span>
    </h5>
    <div class="card-body">
        @can('logInteraction', $data)
            <form action="{{ route('contact.interactions.store', $data->id) }}" method="POST" class="mb-4 border-bottom pb-4">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="interaction-type">{{ __('Type') }}</label>
                        <select name="type" id="interaction-type" class="form-select @error('type') is-invalid @enderror" required>
                            @foreach ($interactionTypes as $case)
                                <option value="{{ $case->value }}" @selected(old('type') === $case->value)>{{ $case->name }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="occurred_at">{{ __('Date') }}</label>
                        <input type="datetime-local" name="occurred_at" id="occurred_at" class="form-control @error('occurred_at') is-invalid @enderror"
                            value="{{ old('occurred_at', now()->format('Y-m-d\TH:i')) }}" required>
                        @error('occurred_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @if (auth()->user()->currentTeam?->hasModule('opportunities') && $contactOpportunities->isNotEmpty())
                        <div class="col-md-6">
                            <label class="form-label" for="opportunity_id">{{ __('Opportunity') }} ({{ __('optional') }})</label>
                            <select name="opportunity_id" id="opportunity_id" class="form-select">
                                <option value="">{{ __('None') }}</option>
                                @foreach ($contactOpportunities as $opp)
                                    <option value="{{ $opp->id }}" @selected(old('opportunity_id') == $opp->id)>{{ $opp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-md-6">
                        <label class="form-label" for="subject">{{ __('Subject') }}</label>
                        <input type="text" name="subject" id="subject" class="form-control" value="{{ old('subject') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="body">{{ __('Details') }}</label>
                        <textarea name="body" id="body" class="form-control" rows="3">{{ old('body') }}</textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">{{ __('Save interaction') }}</button>
                    </div>
                </div>
            </form>
        @endcan

        <h6 class="mb-3">{{ __('History') }}</h6>
        <ul class="timeline mb-0 ms-1">
            @forelse ($data->contactInteractions as $interaction)
                <li class="timeline-item timeline-item-transparent pb-3">
                    <span class="timeline-point timeline-point-primary"></span>
                    <div class="timeline-event">
                        <div class="timeline-header mb-1">
                            <h6 class="mb-0">{{ $interaction->type->name }} @if($interaction->subject) — {{ $interaction->subject }} @endif</h6>
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
    </div>
</div>
