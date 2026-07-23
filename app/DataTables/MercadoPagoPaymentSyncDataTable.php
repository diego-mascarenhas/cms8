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
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'payments.syncs.mercadopago.action')
            ->setRowId('id')
            ->rawColumns(['action', 'payer', 'external_id'])
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

                return number_format($amount, 2, ',', '.').' '.$currency;
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
            ->editColumn('description', function (PaymentSync $sync): string
            {
                return filled($sync->description) ? (string) $sync->description : '—';
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

        return $model->newQuery()
            ->where('team_id', $teamId)
            ->where('provider', 'mercadopago')
            ->where('status', 'approved')
            ->whereNotExists(function ($query): void
            {
                $query->from('payments')
                    ->whereColumn('payments.team_id', 'payment_syncs.team_id')
                    ->where('payments.source_provider', 'mercadopago')
                    ->where(function ($inner): void
                    {
                        $inner->whereColumn('payments.source_reference_id', 'payment_syncs.external_id')
                            ->orWhereRaw("payments.source_reference_id LIKE payment_syncs.external_id || ':%'");
                    });
            });
    }

    public function html(): HtmlBuilder
    {
        return $this
            ->builder()
            ->setTableId('mercadopago-payment-sync-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'desc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'select' => false,
                'autoWidth' => false,
                'drawCallback' => 'function() {
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
            Column::make('description')
                ->title(__('payment_sync.mercadopago.columns.description'))
                ->addClass('min-desktop'),
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
}
