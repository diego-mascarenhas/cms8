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
    $hiddenColumns = [1, 3, 5, 11, $linkColumn];
    $currencyHeaderColumns = [7, 8, 9];
    $stackedUnder = [0 => 1, 2 => 3, 4 => 5];
    $columnWidths = [
        0 => '18%',
        2 => '20%',
        4 => '12%',
        6 => '11%',
        7 => '11%',
        8 => '8%',
        9 => '12%',
        10 => '8%',
    ];
    $formatAmount = static fn (float $amount): string => number_format($amount, 2, ',', '.');
    $reportCards = [
        'sell' => ['label' => __('Sales invoices'), 'icon' => 'ti-arrow-up', 'tone' => 'success'],
        'buy' => ['label' => __('Purchase invoices'), 'icon' => 'ti-arrow-down', 'tone' => 'danger'],
        'sell_credit_notes' => ['label' => __('Sales credit notes'), 'icon' => 'ti-receipt-refund', 'tone' => 'warning'],
        'buy_credit_notes' => ['label' => __('Purchase credit notes'), 'icon' => 'ti-receipt-refund', 'tone' => 'info'],
    ];
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

    <div class="row g-4 mb-4">
        @foreach ($reportCards as $key => $card)
            @php
                $summary = $books[$key]['summary'];
            @endphp
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100 mb-0">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <span>{{ $card['label'] }}</span>
                                <div class="d-flex align-items-center my-2">
                                    <h3 class="mb-0 me-2">{{ $formatAmount($summary['total']) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small></h3>
                                </div>
                                <p class="mb-1 text-muted">{{ __('Taxable base') }} {{ $formatAmount($summary['subtotal']) }}</p>
                                <p class="mb-0 text-muted">{{ __('Tax') }} {{ $formatAmount($summary['tax']) }}</p>
                            </div>
                            <div class="avatar">
                                <span class="avatar-initial rounded bg-label-{{ $card['tone'] }}">
                                    <i class="ti {{ $card['icon'] }} ti-sm"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <span>{{ __('Output VAT for tax filing') }}</span>
                    <h3 class="my-2 mb-0">{{ $formatAmount($outputVat) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <span>{{ __('Input VAT for tax filing') }}</span>
                    <h3 class="my-2 mb-0">{{ $formatAmount($inputVat) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <span>{{ $vatBalance >= 0 ? __('VAT payable') : __('VAT credit') }}</span>
                    <h3 class="my-2 mb-0 {{ $vatBalance >= 0 ? 'text-danger' : 'text-success' }}">{{ $formatAmount(abs($vatBalance)) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">{{ __('Bank statements') }}</h5>
            <p class="text-muted small mb-0">{{ $periodLabel }}</p>
        </div>
        <div class="card-body">
            @if ($statements === [])
                <p class="text-muted mb-0">{{ __('No bank statements for this period.') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Account') }}</th>
                                <th>{{ __('Period') }}</th>
                                <th>{{ __('File') }}</th>
                                <th class="text-end">{{ __('Book amount') }}</th>
                                <th class="text-end">{{ __('Statement amount') }}</th>
                                <th class="text-end">{{ __('Difference') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($statements as $statement)
                                <tr>
                                    <td>{{ $statement['account'] }}</td>
                                    <td>{{ $statement['period'] }}</td>
                                    <td>
                                        <a href="{{ $statement['url'] }}" class="text-body d-inline-flex align-items-center gap-1">
                                            <i class="ti ti-download"></i>
                                            <span>{{ $statement['filename'] }}</span>
                                        </a>
                                    </td>
                                    <td class="text-end">{{ $formatAmount($statement['book']) }}</td>
                                    <td class="text-end">{{ $statement['statement'] === null ? '—' : $formatAmount($statement['statement']) }}</td>
                                    <td class="text-end {{ $statement['balanced'] ? 'text-success' : 'text-danger' }}">
                                        @if ($statement['difference'] === null)
                                            —
                                        @elseif ($statement['balanced'])
                                            {{ __('Balanced') }}
                                        @else
                                            {{ $formatAmount($statement['difference']) }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3" id="hacienda-book-switcher">
        @foreach ($tabs as $key => $label)
            <button type="button" class="btn btn-sm {{ $loop->first ? 'btn-primary' : 'btn-outline-primary' }}" data-hacienda-book="{{ $key }}">
                {{ $label }}
                <span class="badge {{ $loop->first ? 'bg-white text-primary' : 'bg-label-secondary' }} ms-1">{{ count($books[$key]['rows']) }}</span>
            </button>
        @endforeach
    </div>

    <div class="border rounded bg-white">
        @foreach ($tabs as $key => $label)
            <div id="hacienda-pane-{{ $key }}" @class(['d-none' => $key !== 'sell'])>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 small w-100" style="table-layout: fixed">
                        <colgroup>
                            @foreach ($headers as $index => $header)
                                @continue (in_array($index, $hiddenColumns, true))
                                <col style="width: {{ $columnWidths[$index] }}">
                            @endforeach
                        </colgroup>
                        <thead>
                            <tr>
                                @foreach ($headers as $index => $header)
                                    @continue (in_array($index, $hiddenColumns, true))
                                    <th @class([
                                        'text-end' => in_array($index, $amountColumns, true),
                                        'text-break' => in_array($index, [0, 2], true),
                                    ])>{{ in_array($index, $currencyHeaderColumns, true) ? trim((string) preg_replace('/\s*\(.*\)$/', '', $header)) : $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($books[$key]['rows'] as $rowIndex => $row)
                                <tr>
                                    @foreach ($row as $index => $cell)
                                        @continue (in_array($index, $hiddenColumns, true))
                                        <td @class([
                                            'text-end' => in_array($index, $amountColumns, true),
                                            'text-break' => in_array($index, [0, 2], true),
                                        ])>
                                            @if ($index === 0)
                                                <div class="d-flex align-items-start gap-1">
                                                    @if (($row[$linkColumn] ?? '') !== '')
                                                        @php
                                                            $invoiceId = $books[$key]['invoice_ids'][$rowIndex] ?? null;
                                                            $href = $invoiceId
                                                                ? route('hacienda.public.file', ['hash' => $hash, 'invoice' => $invoiceId])
                                                                : $row[$linkColumn];
                                                        @endphp
                                                        <a href="{{ $href }}" class="flex-shrink-0 lh-1" @if (! $invoiceId) target="_blank" rel="noopener" @endif title="PDF">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="11" height="14" viewBox="0 0 32 40" aria-hidden="true">
                                                                <path fill="#E31C23" d="M2 3.5A3.5 3.5 0 0 1 5.5 0H20l12 12v24.5A3.5 3.5 0 0 1 28.5 40h-23A3.5 3.5 0 0 1 2 36.5Z" />
                                                                <path fill="#fff" d="M20 0v8.5A3.5 3.5 0 0 0 23.5 12H32z" />
                                                                <text x="16" y="29" text-anchor="middle" fill="#fff" font-family="Arial, Helvetica, sans-serif" font-size="11" font-weight="700">PDF</text>
                                                            </svg>
                                                        </a>
                                                    @endif
                                                    <div class="min-w-0">
                                                        {{ $cell }}
                                                        @if (($row[1] ?? '') !== '')
                                                            <div class="small text-muted">{{ $row[1] }}</div>
                                                        @endif
                                                        @if (($row[11] ?? '') !== '')
                                                            <div class="small text-muted">({{ $row[11] }})</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @else
                                                {{ $cell }}
                                                @if (isset($stackedUnder[$index]) && ($row[$stackedUnder[$index]] ?? '') !== '')
                                                    <div class="small text-muted">{{ $row[$stackedUnder[$index]] }}</div>
                                                @endif
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($headers) - count($hiddenColumns) }}" class="text-muted">{{ __('No records for this period.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($books[$key]['rows'] !== [])
                            <tfoot>
                                <tr>
                                    @foreach ($books[$key]['totals'] as $index => $cell)
                                        @continue (in_array($index, $hiddenColumns, true))
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

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var active = 'sell';
        var switcher = document.getElementById('hacienda-book-switcher');

        function render() {
            switcher.querySelectorAll('[data-hacienda-book]').forEach(function (button) {
                var on = button.getAttribute('data-hacienda-book') === active;
                button.classList.toggle('btn-primary', on);
                button.classList.toggle('btn-outline-primary', !on);
                var badge = button.querySelector('.badge');
                badge.classList.toggle('bg-white', on);
                badge.classList.toggle('text-primary', on);
                badge.classList.toggle('bg-label-secondary', !on);
            });
            document.querySelectorAll('[id^="hacienda-pane-"]').forEach(function (pane) {
                pane.classList.toggle('d-none', pane.id !== 'hacienda-pane-' + active);
            });
        }

        switcher.addEventListener('click', function (event) {
            var button = event.target.closest('[data-hacienda-book]');
            if (!button) {
                return;
            }
            active = button.getAttribute('data-hacienda-book');
            render();
        });
    });
</script>
@endpush
