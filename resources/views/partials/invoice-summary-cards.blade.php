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
            'title' => 'Pendientes de pago',
            'subtitle' => 'Saldo pendiente',
            'icon' => 'ti-alert-circle',
            'color' => 'warning',
            'count_singular' => 'factura',
            'count_plural' => 'facturas',
            'filter_title' => 'Filtrar pendientes de pago',
        ],
        'credit_notes' => [
            'title' => 'Notas de crédito',
            'subtitle' => 'Últimos 30 días',
            'icon' => 'ti-receipt-refund',
            'color' => 'info',
            'count_singular' => 'nota',
            'count_plural' => 'notas',
            'filter_title' => 'Filtrar notas de crédito',
        ],
        'collected' => [
            'title' => 'Cobradas',
            'subtitle' => 'Últimos 30 días',
            'icon' => 'ti-circle-check',
            'color' => 'success',
            'count_singular' => 'factura',
            'count_plural' => 'facturas',
            'filter_title' => 'Filtrar cobradas',
        ],
        'overdue' => [
            'title' => 'Vencidas',
            'subtitle' => 'Vencimiento superado',
            'icon' => 'ti-clock-exclamation',
            'color' => 'danger',
            'count_singular' => 'factura',
            'count_plural' => 'facturas',
            'filter_title' => 'Filtrar vencidas',
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
