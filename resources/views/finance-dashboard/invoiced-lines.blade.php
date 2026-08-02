@extends('layouts/layoutMaster')

@section('title', $pageTitle)

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ $pageTitle }}</h4>
        <p class="text-muted mb-0">{{ $pageSubtitle }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ $backUrl }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back') }}
        </a>
    </div>
</div>

<div class="mb-4">
    @include('partials.invoiced-lines-period-filter', [
        'filterAction' => route('finance-dashboard.invoiced-lines'),
        'availableYears' => $availableYears,
        'selectedYear' => $selectedYear,
        'selectedMonth' => $selectedMonth,
        'hiddenFields' => array_filter([
            'operation' => request()->query('operation'),
            'return' => request()->query('return'),
            'uncategorized' => ! empty($uncategorizedOnly) ? '1' : null,
        ]),
    ])
</div>

<div class="card">
    <div class="card-body p-0">
        @include('partials.invoiced-line-items-list', [
            'lines' => $lines,
            'totalAmount' => $totalAmount,
            'reportingCurrency' => $reportingCurrency,
            'conversionComplete' => $conversionComplete,
            'amountTone' => $amountTone ?? 'auto',
            'emptyMessage' => $emptyMessage,
        ])
    </div>
</div>
@endsection
