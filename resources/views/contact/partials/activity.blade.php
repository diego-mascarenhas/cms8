@php
    $interactionTypes = \App\Enums\ContactInteractionType::cases();
    $occurredAtDefault = old('occurred_at', now()->format('Y-m-d H:i'));
    $occurredAtDefault = is_string($occurredAtDefault) ? str_replace('T', ' ', $occurredAtDefault) : now()->format('Y-m-d H:i');
    $showOpportunitySelect = auth()->user()->currentTeam?->hasModule('opportunities') && $contactOpportunities->isNotEmpty();
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
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="interaction-type">{{ __('Type') }}</label>
                        <select name="type" id="interaction-type" class="form-select @error('type') is-invalid @enderror" required>
                            @foreach ($interactionTypes as $case)
                                <option value="{{ $case->value }}" @selected(old('type') === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="occurred_at">{{ __('Date') }}</label>
                        <input type="text" name="occurred_at" id="occurred_at" class="form-control @error('occurred_at') is-invalid @enderror"
                            value="{{ $occurredAtDefault }}" autocomplete="off" required>
                        @error('occurred_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="w-100"></div>
                    <div class="{{ $showOpportunitySelect ? 'col-md-6' : 'col-12' }}">
                        <label class="form-label" for="subject">{{ __('Subject') }}</label>
                        <input type="text" name="subject" id="subject" class="form-control" value="{{ old('subject') }}">
                    </div>
                    @if ($showOpportunitySelect)
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

        @include('contact.partials.activity-history')
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('occurred_at');
            if (!el || typeof flatpickr === 'undefined') {
                return;
            }
            var locale = @json(app()->getLocale());
            var initialValue = @json($occurredAtDefault);

            function initOccurredAtPicker() {
                flatpickr(el, {
                    enableTime: true,
                    time_24hr: true,
                    dateFormat: 'Y-m-d H:i',
                    allowInput: true,
                    altInput: true,
                    altFormat: locale === 'en' ? 'Y-m-d H:i' : 'd/m/Y H:i',
                    defaultDate: initialValue,
                    monthSelectorType: 'static'
                });
            }

            if (locale === 'en') {
                initOccurredAtPicker();
                return;
            }
            if (flatpickr.l10ns && flatpickr.l10ns[locale]) {
                flatpickr.localize(flatpickr.l10ns[locale]);
                initOccurredAtPicker();
                return;
            }
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/' + locale + '.js';
            script.onload = function () {
                if (flatpickr.l10ns && flatpickr.l10ns[locale]) {
                    flatpickr.localize(flatpickr.l10ns[locale]);
                }
                initOccurredAtPicker();
            };
            script.onerror = initOccurredAtPicker;
            document.head.appendChild(script);
        });
    </script>
@endpush
