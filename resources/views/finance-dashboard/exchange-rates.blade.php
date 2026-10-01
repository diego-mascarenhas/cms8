@extends('layouts/layoutMaster')

@section('title', __('Exchange rates'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Accounting Dashboard') }}/</span> {{ __('Exchange rates') }}</h4>
        <p class="text-muted mb-0">{{ __('Each invoice uses the last published rate on or before its date.') }}</p>
        @if ($exchangeRates['updated_at'])
            <p class="text-muted mb-0">{{ __('Updated :datetime', ['datetime' => $exchangeRates['updated_at']->timezone('Europe/Madrid')->format('d/m/Y H:i')]) }}</p>
        @endif
    </div>
    <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2 align-items-center">
        <form method="GET" action="{{ route('finance-dashboard.exchange-rates') }}" class="d-flex align-items-center">
            <label for="exchange-rates-year" class="form-label mb-0 me-2">{{ __('Year') }}</label>
            <div class="position-relative w-px-100">
                <select id="exchange-rates-year" name="year" class="select2 form-select js-filter-select" onchange="this.form.submit()">
                    @foreach($availableYears as $year)
                        <option value="{{ $year }}" @selected($year === $selectedYear)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        @include('partials.filter-select2-script')
        <a href="{{ route('finance-dashboard.index') }}" class="btn btn-outline-secondary">{{ __('Accounting Dashboard') }}</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th class="text-end">USD/ARS</th>
                    <th class="text-end">USD/EUR</th>
                    <th class="text-end">EUR/ARS</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $date => $row)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</td>
                        <td class="text-end">{{ isset($row['ARS']) ? number_format($row['ARS'], 4, ',', '.') : '' }}</td>
                        <td class="text-end">{{ isset($row['EUR']) ? number_format($row['EUR'], 6, ',', '.') : '' }}</td>
                        <td class="text-end">{{ ! empty($row['ars_eur']) ? number_format(1 / $row['ars_eur'], 4, ',', '.') : '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-muted">{{ __('No exchange rates for this year.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
