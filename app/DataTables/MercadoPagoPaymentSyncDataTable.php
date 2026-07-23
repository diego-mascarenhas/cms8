<?php

namespace App\DataTables;

use App\Models\PaymentSync;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class MercadoPagoPaymentSyncDataTable extends DataTable
{
    /** @var array<string, array{invoice_id: ?int, number: ?string, stripe_external_id?: string}>|null */
    private ?array $stripeLinkedInvoices = null;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $stripeLinkedInvoices = $this->stripeLinkedInvoices();

        return (new EloquentDataTable($query))
            ->addColumn('action', function (PaymentSync $sync) use ($stripeLinkedInvoices): string
            {
                $linked = $sync->linkedStripeInvoice($stripeLinkedInvoices);
                $invoiceId = $linked['invoice_id'] ?? null;
                $invoiceNumber = $linked['number'] ?? null;

                if ($invoiceId === null)
                {
                    $importedPayment = $sync->importedMercadoPagoPayment();
                    if ($importedPayment?->invoice_id)
                    {
                        $invoiceId = (int) $importedPayment->invoice_id;
                        $invoiceNumber = $importedPayment->invoice?->number ?? $invoiceNumber;
                    }
                }

                return view('payments.syncs.mercadopago.action', [
                    'id' => $sync->id,
                    'isStripeLinked' => $linked !== null || $invoiceId !== null,
                    'linkedInvoiceId' => $invoiceId,
                    'linkedInvoiceNumber' => $invoiceNumber,
                ])->render();
            })
            ->setRowId('id')
            ->rawColumns(['action', 'payer', 'external_id', 'transaction_indicator'])
            ->addColumn('transaction_indicator', function (PaymentSync $sync): string
            {
                return $sync->transactionType()->badge();
            })
            ->editColumn('charge_created_at', function (PaymentSync $sync): string
            {
                if ($sync->charge_created_at === null)
                {
                    return '—';
                }

                return Carbon::parse($sync->charge_created_at)->format('d/m/Y');
            })
            ->addColumn('amount_label', function (PaymentSync $sync): string
            {
                $currency = strtoupper((string) $sync->currency);
                $cents = (int) $sync->amount_net_cents;
                $amount = in_array($currency, ['CLP', 'UYU', 'PYG'], true)
                    ? (float) $cents
                    : round($cents / 100, 2);

                return e(number_format($amount, 2, ',', '.').' '.$currency);
            })
            ->addColumn('payer', function (PaymentSync $sync): string
            {
                if ($sync->lacksIdentifiablePayer())
                {
                    return '<span class="text-muted">'.e(__('payment_sync.mercadopago.payer_unknown')).'</span>';
                }

                $parts = [];
                if (filled($sync->customer_id))
                {
                    $parts[] = '<code>'.e((string) $sync->customer_id).'</code>';
                }
                if (filled($sync->customer_email))
                {
                    $parts[] = '<span class="text-muted">'.e((string) $sync->customer_email).'</span>';
                }

                return $parts === [] ? '—' : implode(' <span class="text-muted">·</span> ', $parts);
            })
            ->editColumn('external_id', function (PaymentSync $sync): string
            {
                return '<code>'.e((string) $sync->external_id).'</code>';
            })
            ->filterColumn('payer', function ($query, $keyword): void
            {
                $keyword = trim((string) $keyword);
                if ($keyword === '')
                {
                    return;
                }

                $query->where(function ($inner) use ($keyword): void
                {
                    $inner->where('payment_syncs.customer_id', 'like', '%'.$keyword.'%')
                        ->orWhere('payment_syncs.customer_email', 'like', '%'.$keyword.'%');
                });
            })
            ->filterColumn('amount_label', function ($query, $keyword): void
            {
                $keyword = trim((string) $keyword);
                if ($keyword === '')
                {
                    return;
                }

                $query->where(function ($inner) use ($keyword): void
                {
                    $inner->where('payment_syncs.currency', 'like', '%'.$keyword.'%')
                        ->orWhere('payment_syncs.amount_net_cents', 'like', '%'.preg_replace('/\D+/', '', $keyword).'%');
                });
            })
            ->orderColumn('charge_created_at', function ($query, $order): void
            {
                $direction = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';

                $query->orderByRaw('payment_syncs.charge_created_at IS NULL')
                    ->orderBy('payment_syncs.charge_created_at', $direction)
                    ->orderBy('payment_syncs.id', $direction);
            });
    }

    public function query(PaymentSync $model): QueryBuilder
    {
        $teamId = (int) auth()->user()->currentTeam->id;
        $assignmentFilter = $this->assignmentFilter();

        $query = $model->newQuery()
            ->where('team_id', $teamId)
            ->where('provider', 'mercadopago')
            ->where('status', 'approved');

        $notImported = function ($sub): void
        {
            $sub->from('payments')
                ->whereColumn('payments.team_id', 'payment_syncs.team_id')
                ->where('payments.source_provider', 'mercadopago')
                ->where(function ($inner): void
                {
                    $inner->whereColumn('payments.source_reference_id', 'payment_syncs.external_id')
                        ->orWhereRaw("payments.source_reference_id LIKE payment_syncs.external_id || ':%'");
                });
        };

        $stripeLinked = $this->stripeLinkedExistsCallback($teamId);

        if ($assignmentFilter === 'stripe')
        {
            $query->whereExists($stripeLinked);
        } elseif ($assignmentFilter === 'all')
        {
            // Pending queue + Stripe-linked rows (even after import).
            $query->where(function ($outer) use ($notImported, $stripeLinked): void
            {
                $outer->whereNotExists($notImported)
                    ->orWhereExists($stripeLinked);
            });
        } else
        {
            $query->whereNotExists($notImported)
                ->whereNotExists($stripeLinked);
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        $assignmentLabel = e(__('payment_sync.mercadopago.filters.assignment'));
        $unassignedLabel = e(__('payment_sync.mercadopago.filters.unassigned'));
        $stripeLabel = e(__('payment_sync.mercadopago.filters.stripe_linked'));
        $allLabel = e(__('payment_sync.mercadopago.filters.all'));

        $initComplete = "function () {
    var api = this.api();
    var f = jQuery('#mercadopago-payment-sync-table_filter');
    if (! f.length) { return; }
    f.addClass('d-flex flex-wrap align-items-center justify-content-between column-gap-3 row-gap-2');
    if (! jQuery('#mp-sync-assignment-filter').length) {
        f.prepend(
            '<div class=\"d-inline-flex align-items-center flex-shrink-0\">' +
            '<label for=\"mp-sync-assignment-filter\" class=\"form-label mb-0 me-2 text-nowrap\">{$assignmentLabel}</label>' +
            '<select id=\"mp-sync-assignment-filter\" class=\"form-select form-select-sm\" style=\"min-width:12rem;max-width:14rem;\">' +
            '<option value=\"unassigned\" selected>{$unassignedLabel}</option>' +
            '<option value=\"stripe\">{$stripeLabel}</option>' +
            '<option value=\"all\">{$allLabel}</option>' +
            '</select></div>'
        );
    }
    f.find('label').addClass('ms-auto mb-0');
    jQuery('#mp-sync-assignment-filter').off('change.mpAssignment').on('change.mpAssignment', function () {
        api.ajax.reload();
    });
}";

        return $this
            ->builder()
            ->setTableId('mercadopago-payment-sync-table')
            ->columns($this->getColumns())
            ->minifiedAjax(
                '',
                "data.assignment_filter = ($('#mp-sync-assignment-filter').val() || 'unassigned');",
            )
            ->dom('frtip')
            ->orderBy(2, 'desc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'select' => false,
                'autoWidth' => false,
                'initComplete' => $initComplete,
                'drawCallback' => 'function() {
                    var f = jQuery("#mercadopago-payment-sync-table_filter");
                    f.addClass("d-flex flex-wrap align-items-center justify-content-between column-gap-3 row-gap-2");
                    f.find("label").addClass("ms-auto mb-0");
                    $("#mercadopago-payment-sync-table tbody tr").css({
                        "user-select": "none",
                        "-webkit-user-select": "none",
                        "-moz-user-select": "none",
                        "-ms-user-select": "none"
                    });
                }',
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::computed('transaction_indicator')
                ->title('')
                ->addClass('all')
                ->width(30)
                ->searchable(false)
                ->orderable(false)
                ->className('text-center'),
            Column::make('charge_created_at')
                ->title(__('payment_sync.mercadopago.columns.date'))
                ->addClass('min-tablet')
                ->className('text-center'),
            Column::computed('amount_label')
                ->title(__('payment_sync.mercadopago.columns.amount'))
                ->addClass('min-tablet')
                ->className('text-end')
                ->orderable(false)
                ->searchable(true),
            Column::computed('payer')
                ->title(__('payment_sync.mercadopago.columns.payer'))
                ->addClass('all')
                ->orderable(false)
                ->searchable(true),
            Column::make('external_id')
                ->title(__('payment_sync.mercadopago.columns.external_id'))
                ->addClass('min-tablet'),
            Column::computed('action')
                ->title(__('payment_sync.mercadopago.columns.actions'))
                ->exportable(false)
                ->printable(false)
                ->addClass('all')
                ->className('text-center')
                ->orderable(false)
                ->searchable(false),
        ];
    }

    protected function filename(): string
    {
        return 'MercadoPagoPaymentSync_'.date('YmdHis');
    }

    /**
     * @return array<string, array{invoice_id: ?int, number: ?string, stripe_external_id?: string}>
     */
    private function stripeLinkedInvoices(): array
    {
        if ($this->stripeLinkedInvoices !== null)
        {
            return $this->stripeLinkedInvoices;
        }

        $teamId = (int) auth()->user()->currentTeam->id;

        return $this->stripeLinkedInvoices = PaymentSync::stripeLinkedInvoiceMap($teamId);
    }

    private function assignmentFilter(): string
    {
        $filter = strtolower(trim((string) request()->input('assignment_filter', 'unassigned')));

        return in_array($filter, ['unassigned', 'stripe', 'all'], true)
            ? $filter
            : 'unassigned';
    }

    /**
     * @return \Closure(\Illuminate\Database\Query\Builder): void
     */
    private function stripeLinkedExistsCallback(int $teamId): \Closure
    {
        return function ($sub) use ($teamId): void
        {
            $sub->from('invoice_syncs')
                ->whereColumn('invoice_syncs.team_id', 'payment_syncs.team_id')
                ->where('invoice_syncs.team_id', $teamId)
                ->where('invoice_syncs.provider', 'stripe')
                ->whereRaw("NULLIF(TRIM(invoice_syncs.raw_payload->'metadata'->>'payment_reference'), '') IS NOT NULL")
                ->where(function ($match): void
                {
                    $match->whereRaw(
                        "TRIM(invoice_syncs.raw_payload->'metadata'->>'payment_reference') = payment_syncs.external_id",
                    )->orWhereRaw(
                        "NULLIF(TRIM(COALESCE(payment_syncs.raw_payload->'transaction_details'->>'transaction_id', '')), '') IS NOT NULL
                        AND TRIM(invoice_syncs.raw_payload->'metadata'->>'payment_reference')
                            = TRIM(payment_syncs.raw_payload->'transaction_details'->>'transaction_id')",
                    )->orWhereRaw(
                        "NULLIF(TRIM(COALESCE(payment_syncs.raw_payload->'point_of_interaction'->'transaction_data'->>'e2e_id', '')), '') IS NOT NULL
                        AND TRIM(invoice_syncs.raw_payload->'metadata'->>'payment_reference')
                            = TRIM(payment_syncs.raw_payload->'point_of_interaction'->'transaction_data'->>'e2e_id')",
                    )->orWhereRaw(
                        "NULLIF(TRIM(COALESCE(payment_syncs.raw_payload->'point_of_interaction'->'transaction_data'->>'transaction_id', '')), '') IS NOT NULL
                        AND TRIM(invoice_syncs.raw_payload->'metadata'->>'payment_reference')
                            = TRIM(payment_syncs.raw_payload->'point_of_interaction'->'transaction_data'->>'transaction_id')",
                    );
                });
        };
    }
}
