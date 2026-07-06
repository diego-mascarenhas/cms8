@once
    <style>
        .invoice-summary-card-subtitle {
            min-height: 1.125rem;
        }
    </style>
@endonce

@php
    $visibleFilters = $visibleFilters ?? ['unpaid', 'credit_notes', 'collected', 'overdue'];
    $linkToInvoiceList = $linkToInvoiceList ?? false;

    $invoiceSummaryCards = [
        'unpaid' => [
            'title' => __('app.invoice_summary_unpaid_title'),
            'subtitle' => __('app.invoice_summary_unpaid_subtitle'),
            'icon' => 'ti-alert-circle',
            'color' => 'warning',
            'count_singular' => __('app.invoice_summary_count_invoice_singular'),
            'count_plural' => __('app.invoice_summary_count_invoice_plural'),
            'filter_title' => __('app.invoice_summary_unpaid_filter'),
        ],
        'credit_notes' => [
            'title' => __('app.invoice_summary_credit_notes_title'),
            'subtitle' => __('app.invoice_summary_credit_notes_subtitle'),
            'icon' => 'ti-receipt-refund',
            'color' => 'info',
            'count_singular' => __('app.invoice_summary_count_note_singular'),
            'count_plural' => __('app.invoice_summary_count_note_plural'),
            'filter_title' => __('app.invoice_summary_credit_notes_filter'),
        ],
        'collected' => [
            'title' => __('app.invoice_summary_collected_title'),
            'subtitle' => __('app.invoice_summary_collected_subtitle'),
            'icon' => 'ti-circle-check',
            'color' => 'success',
            'count_singular' => __('app.invoice_summary_count_invoice_singular'),
            'count_plural' => __('app.invoice_summary_count_invoice_plural'),
            'filter_title' => __('app.invoice_summary_collected_filter'),
        ],
        'overdue' => [
            'title' => __('app.invoice_summary_overdue_title'),
            'subtitle' => __('app.invoice_summary_overdue_subtitle'),
            'icon' => 'ti-clock-exclamation',
            'color' => 'danger',
            'count_singular' => __('app.invoice_summary_count_invoice_singular'),
            'count_plural' => __('app.invoice_summary_count_invoice_plural'),
            'filter_title' => __('app.invoice_summary_overdue_filter'),
        ],
    ];
@endphp

<div class="row g-4 {{ $rowClass ?? 'mb-4' }}">
    @foreach ($visibleFilters as $filterKey)
        @php
            $card = $invoiceSummaryCards[$filterKey] ?? null;
            $stats = $invoiceStats[$filterKey] ?? null;
        @endphp
        @if ($card && $stats)
            <div class="{{ $columnClass ?? 'col-sm-6 col-xl-3' }}">
                @if ($linkToInvoiceList)
                    <a href="{{ route('invoice.index', ['summary_filter' => $filterKey]) }}" class="card text-body h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="content-left">
                                    <span>{{ $card['title'] }}</span>
                                    <small class="text-muted d-block invoice-summary-card-subtitle">{{ $card['subtitle'] }}</small>
                                    <div class="d-flex align-items-center my-2">
                                        <h3 class="mb-0 me-2">{{ $stats['amount_label'] }}</h3>
                                    </div>
                                    <p class="mb-0">{{ $stats['count'] }} {{ $stats['count'] === 1 ? $card['count_singular'] : $card['count_plural'] }}</p>
                                </div>
                                <div class="avatar">
                                    <span class="avatar-initial rounded bg-label-{{ $card['color'] }}">
                                        <i class="ti {{ $card['icon'] }} ti-sm"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                @else
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="content-left">
                                    <span>{{ $card['title'] }}</span>
                                    <small class="text-muted d-block invoice-summary-card-subtitle">{{ $card['subtitle'] }}</small>
                                    <div class="d-flex align-items-center my-2">
                                        <h3 class="mb-0 me-2">{{ $stats['amount_label'] }}</h3>
                                    </div>
                                    <p class="mb-0">{{ $stats['count'] }} {{ $stats['count'] === 1 ? $card['count_singular'] : $card['count_plural'] }}</p>
                                </div>
                                <div class="avatar">
                                    <a href="#" class="avatar-initial rounded bg-label-{{ $card['color'] }} filter-invoice-summary" data-filter="{{ $filterKey }}" title="{{ $card['filter_title'] }}">
                                        <i class="ti {{ $card['icon'] }} ti-sm"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    @endforeach
</div>
