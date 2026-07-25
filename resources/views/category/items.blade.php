@extends('layouts/layoutMaster')

@section('title', __('Category invoiced lines'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Category') }}/</span>
            {{ $category->name }}
        </h4>
        <p class="text-muted mb-0">{{ __('Invoiced lines in this category') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ $backUrl }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back') }}
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
    </div>
@endif

<div class="mb-4">
    @include('partials.invoiced-lines-period-filter', [
        'filterAction' => route('categories.items', ['id' => $category->id]),
        'availableYears' => $availableYears,
        'selectedYear' => $selectedYear,
        'selectedMonth' => $selectedMonth,
        'hiddenFields' => array_filter([
            'operation' => $operation ?? request()->query('operation'),
            'return' => request()->query('return'),
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
            'emptyMessage' => __('No invoiced lines in this category.'),
        ])
    </div>
</div>
@endsection
