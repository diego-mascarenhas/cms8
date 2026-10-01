@extends('layouts/blankLayout')

@section('title', __('Hacienda'))

@php
    $tabs = [
        'sell' => __('Sales invoices'),
        'buy' => __('Purchase invoices'),
        'sell_credit_notes' => __('Sales credit notes'),
        'buy_credit_notes' => __('Purchase credit notes'),
    ];
    $linkColumn = count($headers) - 1;
    $amountColumns = [4, 7, 8, 9];
@endphp

@section('content')
<div class="container-xxl flex-grow-1 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div>
            <h4 class="mb-1">{{ $teamName }}</h4>
            <p class="text-muted mb-0">{{ $periodLabel }}</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2">
            @include('partials.vat-period-selector')
            <a href="{{ route('hacienda.public.export', ['hash' => $hash, 'vat_year' => $vatYear, 'vat_period' => $vatPeriod]) }}" class="btn btn-primary">
                <i class="ti ti-download me-1"></i> {{ __('Export') }}
            </a>
        </div>
    </div>

    <ul class="nav nav-tabs" role="tablist">
        @foreach ($tabs as $key => $label)
            <li class="nav-item" role="presentation">
                <button class="nav-link @if ($loop->first) active @endif" id="hacienda-tab-{{ $key }}" data-bs-toggle="tab" data-bs-target="#hacienda-pane-{{ $key }}" type="button" role="tab" aria-controls="hacienda-pane-{{ $key }}" @if ($loop->first) aria-selected="true" @else aria-selected="false" @endif>
                    {{ $label }}
                    <span class="badge bg-label-secondary ms-1">{{ count($books[$key]['rows']) }}</span>
                </button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom bg-white">
        @foreach ($tabs as $key => $label)
            <div class="tab-pane fade @if ($loop->first) show active @endif" id="hacienda-pane-{{ $key }}" role="tabpanel" aria-labelledby="hacienda-tab-{{ $key }}">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                @foreach ($headers as $index => $header)
                                    <th @class([
                                        'text-center' => $index === $linkColumn,
                                        'text-end' => in_array($index, $amountColumns, true),
                                    ])>{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($books[$key]['rows'] as $rowIndex => $row)
                                <tr>
                                    @foreach ($row as $index => $cell)
                                        <td @class([
                                            'text-center' => $index === $linkColumn,
                                            'text-end' => in_array($index, $amountColumns, true),
                                        ])>
                                            @if ($index === $linkColumn && $cell !== '')
                                                @php
                                                    $invoiceId = $books[$key]['invoice_ids'][$rowIndex] ?? null;
                                                    $href = $invoiceId
                                                        ? route('hacienda.public.file', ['hash' => $hash, 'invoice' => $invoiceId])
                                                        : $cell;
                                                @endphp
                                                <a href="{{ $href }}" @if (! $invoiceId) target="_blank" rel="noopener" @endif title="PDF">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="20" viewBox="0 0 32 40" aria-hidden="true">
                                                        <path fill="#E31C23" d="M2 3.5A3.5 3.5 0 0 1 5.5 0H20l12 12v24.5A3.5 3.5 0 0 1 28.5 40h-23A3.5 3.5 0 0 1 2 36.5Z" />
                                                        <path fill="#fff" d="M20 0v8.5A3.5 3.5 0 0 0 23.5 12H32z" />
                                                        <text x="16" y="29" text-anchor="middle" fill="#fff" font-family="Arial, Helvetica, sans-serif" font-size="10" font-weight="700">PDF</text>
                                                    </svg>
                                                </a>
                                            @else
                                                {{ $cell }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($headers) }}" class="text-muted">{{ __('No records for this period.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($books[$key]['rows'] !== [])
                            <tfoot>
                                <tr>
                                    @foreach ($books[$key]['totals'] as $index => $cell)
                                        <th @class(['text-end' => in_array($index, $amountColumns, true)])>{{ $cell }}</th>
                                    @endforeach
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
