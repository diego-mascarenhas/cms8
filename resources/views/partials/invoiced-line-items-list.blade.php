@php
    $formatAmount = static fn (float $amount): string => \App\Helpers\Helpers::formatDecimal($amount, 0);
    $reportingCurrency = $reportingCurrency ?? strtoupper((string) config('verifactu.default_currency', 'EUR'));
    $lines = $lines ?? [];
@endphp

@if(! ($conversionComplete ?? true) && $lines !== [])
    <div class="alert alert-warning m-3 mb-0" role="alert">
        {{ __('Some lines could not be converted to :currency. Check exchange rates.', ['currency' => $reportingCurrency]) }}
    </div>
@endif

@if($lines === [])
    <p class="text-muted mb-0 p-4">{{ $emptyMessage ?? __('No invoiced lines found.') }}</p>
@else
    <ul class="list-group list-group-flush">
        @foreach($lines as $line)
            <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                <div class="flex-grow-1 min-w-0">
                    @if(filled($line['description']))
                        <strong class="d-block text-truncate">{{ $line['description'] }}</strong>
                    @endif
                    @if($line['enterprise_id'])
                        <a href="{{ route('client.show', $line['enterprise_id']) }}" class="text-muted small">
                            {{ $line['enterprise_name'] }}
                        </a>
                    @else
                        <small class="text-muted">{{ $line['enterprise_name'] }}</small>
                    @endif
                    @if(filled($line['category_name']))
                        <small class="text-muted d-block">{{ $line['category_name'] }}</small>
                    @endif
                </div>
                <div class="text-end text-nowrap">
                    @if($line['has_discount'])
                        <small class="text-muted d-block">
                            {{ __('With discount') }}
                            @if($line['discount_amount'] !== null)
                                ({{ $formatAmount((float) $line['discount_amount']) }} {{ $reportingCurrency }})
                            @endif
                        </small>
                    @endif
                    @php
                        $amountClass = ($amountTone ?? 'auto') === 'income'
                            ? 'text-success'
                            : (($amountTone ?? 'auto') === 'expense' ? 'text-danger' : 'text-body');
                    @endphp
                    <span class="{{ $amountClass }} fw-medium">
                        {{ $formatAmount((float) $line['amount']) }} {{ $reportingCurrency }}
                    </span>
                </div>
            </li>
        @endforeach
    </ul>
    <div class="p-4 border-top text-end">
        <h4 class="mb-0">
            <strong>{{ __('Total') }}: {{ $formatAmount((float) ($totalAmount ?? 0)) }} {{ $reportingCurrency }}</strong>
        </h4>
    </div>
@endif
