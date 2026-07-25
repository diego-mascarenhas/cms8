@php
    $incomeCategories = $incomeCategories ?? [];
    $expenseCategories = $expenseCategories ?? [];
    $reportingCurrency = $reportingCurrency ?? strtoupper((string) config('verifactu.default_currency', 'EUR'));
    $selectedYear = $selectedYear ?? (int) date('Y');
    $selectedMonth = $selectedMonth ?? (int) date('n');
    $formatCategoryAmount = static fn (float $amount): string => \App\Helpers\Helpers::formatDecimal($amount, 0);
    $incomeChartColors = ['#28c76f', '#55d187', '#83df9e', '#b2edc4', '#1f9d57', '#3dd68c', '#6ee7a8', '#9ef0c4'];
    $expenseChartColors = ['#ea5455', '#f08182', '#f5adaf', '#fad8d9', '#d43f3f', '#ff6b6b', '#ff9999', '#ffc9c9'];
    $incomeChartId = $incomeChartId ?? 'incomeCategoryChart';
    $expenseChartId = $expenseChartId ?? 'expenseCategoryChart';
    $invoicedLinesUrl = static fn (string $operation): string => route('finance-dashboard.invoiced-lines', [
        'operation' => $operation,
        'year' => $selectedYear,
        'month' => $selectedMonth,
        'return' => request()->fullUrl(),
    ]);
    $categoryItemsUrl = static function (?int $categoryId, string $operation) use ($selectedYear, $selectedMonth): ?string
    {
        if ($categoryId === null)
        {
            return null;
        }

        return route('categories.items', [
            'id' => $categoryId,
            'operation' => $operation,
            'year' => $selectedYear,
            'month' => $selectedMonth,
            'return' => request()->fullUrl(),
        ]);
    };
@endphp

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="card-title m-0">{{ __('Income by category') }}</h5>
                    <p class="text-muted small mb-0">{{ __('Top invoiced revenue lines') }}</p>
                </div>
                @can('viewAny', App\Models\Invoice::class)
                    <a href="{{ $invoicedLinesUrl('sell') }}" class="btn btn-sm btn-outline-success flex-shrink-0">
                        <i class="ti ti-list-details me-1"></i>{{ __('View all') }}
                    </a>
                @endcan
            </div>
            <div class="card-body">
                @if(count($incomeCategories) === 0)
                    <p class="text-muted mb-0">{{ __('No invoiced income in this period.') }}</p>
                @else
                    <div id="{{ $incomeChartId }}" class="mb-3"></div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Category') }}</th>
                                    <th class="text-end">{{ __('Total') }}</th>
                                    <th class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($incomeCategories as $row)
                                    @php
                                        $swatchColor = $incomeChartColors[$loop->index % count($incomeChartColors)];
                                        $categoryUrl = $categoryItemsUrl($row['id'] ?? null, 'sell');
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="d-inline-flex align-items-center gap-2">
                                                <span class="d-inline-block rounded-circle flex-shrink-0" style="width: 0.625rem; height: 0.625rem; background-color: {{ $swatchColor }}"></span>
                                                @if($categoryUrl)
                                                    <a href="{{ $categoryUrl }}" class="text-body">{{ $row['name'] }}</a>
                                                @else
                                                    <span>{{ $row['name'] }}</span>
                                                @endif
                                            </span>
                                        </td>
                                        <td class="text-end">{{ $formatCategoryAmount($row['total']) }} <span class="text-muted">{{ $reportingCurrency }}</span></td>
                                        <td class="text-end">{{ number_format($row['share_percent'], 1) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="card-title m-0">{{ __('Expenses by category') }}</h5>
                    <p class="text-muted small mb-0">{{ __('Where costs concentrate') }}</p>
                </div>
                @can('viewAny', App\Models\Invoice::class)
                    <a href="{{ $invoicedLinesUrl('buy') }}" class="btn btn-sm btn-outline-danger flex-shrink-0">
                        <i class="ti ti-list-details me-1"></i>{{ __('View all') }}
                    </a>
                @endcan
            </div>
            <div class="card-body">
                @if(count($expenseCategories) === 0)
                    <p class="text-muted mb-0">{{ __('No invoiced expenses in this period.') }}</p>
                @else
                    <div id="{{ $expenseChartId }}" class="mb-3"></div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Category') }}</th>
                                    <th class="text-end">{{ __('Total') }}</th>
                                    <th class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($expenseCategories as $row)
                                    @php
                                        $swatchColor = $expenseChartColors[$loop->index % count($expenseChartColors)];
                                        $categoryUrl = $categoryItemsUrl($row['id'] ?? null, 'buy');
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="d-inline-flex align-items-center gap-2">
                                                <span class="d-inline-block rounded-circle flex-shrink-0" style="width: 0.625rem; height: 0.625rem; background-color: {{ $swatchColor }}"></span>
                                                @if($categoryUrl)
                                                    <a href="{{ $categoryUrl }}" class="text-body">{{ $row['name'] }}</a>
                                                @else
                                                    <span>{{ $row['name'] }}</span>
                                                @endif
                                            </span>
                                        </td>
                                        <td class="text-end">{{ $formatCategoryAmount($row['total']) }} <span class="text-muted">{{ $reportingCurrency }}</span></td>
                                        <td class="text-end">{{ number_format($row['share_percent'], 1) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
