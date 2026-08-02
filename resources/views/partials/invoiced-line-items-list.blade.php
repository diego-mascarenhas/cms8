@php
    $formatAmount = static fn (float $amount): string => \App\Helpers\Helpers::formatDecimal($amount, 0);
    $reportingCurrency = $reportingCurrency ?? strtoupper((string) config('verifactu.default_currency', 'EUR'));
    $lines = $lines ?? [];
    $showDescriptionColumn = collect($lines)->contains(fn (array $line): bool => filled($line['description'] ?? null));
    $showCategoryColumn = collect($lines)->contains(fn (array $line): bool => filled($line['category_name'] ?? null));
    $canEditCategory = ! empty($canEditCategory);
    $amountClass = ($amountTone ?? 'auto') === 'income'
        ? 'text-success'
        : (($amountTone ?? 'auto') === 'expense' ? 'text-danger' : 'text-body');
@endphp

@if(! ($conversionComplete ?? true) && $lines !== [])
    <div class="alert alert-warning m-3 mb-0" role="alert">
        {{ __('Some lines could not be converted to :currency. Check exchange rates.', ['currency' => $reportingCurrency]) }}
    </div>
@endif

@if($lines === [])
    <p class="text-muted mb-0 p-4">{{ $emptyMessage ?? __('No invoiced lines found.') }}</p>
@else
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="invoiced-line-items-table">
            <thead>
                <tr>
                    <th>{{ __('Enterprise') }}</th>
                    @if($showDescriptionColumn)
                        <th>{{ __('Description') }}</th>
                    @endif
                    @if($showCategoryColumn)
                        <th>{{ __('Category') }}</th>
                    @endif
                    <th class="text-end">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lines as $line)
                    @php
                        $hasCategory = filled($line['category_id'] ?? null);
                        $categoryLabel = $hasCategory
                            ? (string) ($line['category_name'] ?? ('#'.$line['category_id']))
                            : __('Uncategorized');
                    @endphp
                    <tr
                        class="invoiced-line-item"
                        data-item-id="{{ $line['id'] ?? '' }}"
                        data-category-id="{{ $line['category_id'] ?? '' }}"
                    >
                        <td>
                            @if($line['enterprise_id'])
                                <a href="{{ route('client.show', $line['enterprise_id']) }}" class="text-body">
                                    {{ $line['enterprise_name'] }}
                                </a>
                            @else
                                <span class="text-muted">{{ $line['enterprise_name'] }}</span>
                            @endif
                        </td>
                        @if($showDescriptionColumn)
                            <td class="text-muted">{{ $line['description'] ?: '—' }}</td>
                        @endif
                        @if($showCategoryColumn)
                            <td>
                                @if($canEditCategory && ! empty($line['id']))
                                    <input type="hidden" class="line-category-id" value="{{ $hasCategory ? $line['category_id'] : '' }}">
                                    <button
                                        type="button"
                                        class="badge border-0 line-category-badge {{ $hasCategory ? 'bg-label-primary' : 'bg-label-secondary' }}"
                                        title="{{ __('Select category') }}"
                                    >
                                        {{ $categoryLabel }}
                                    </button>
                                @else
                                    <span class="text-muted">{{ $categoryLabel }}</span>
                                @endif
                            </td>
                        @endif
                        <td class="text-end text-nowrap">
                            @if($line['has_discount'])
                                <small class="text-muted me-1">
                                    {{ __('With discount') }}
                                    @if($line['discount_amount'] !== null)
                                        ({{ $formatAmount((float) $line['discount_amount']) }} {{ $reportingCurrency }})
                                    @endif
                                </small>
                            @endif
                            <span class="{{ $amountClass }} fw-medium">
                                {{ $formatAmount((float) $line['amount']) }} {{ $reportingCurrency }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="p-4 border-top text-end">
        <h4 class="mb-0">
            <strong>{{ __('Total') }}: {{ $formatAmount((float) ($totalAmount ?? 0)) }} {{ $reportingCurrency }}</strong>
        </h4>
    </div>
@endif
