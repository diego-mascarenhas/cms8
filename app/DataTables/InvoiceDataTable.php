<?php

namespace App\DataTables;

use App\Models\Invoice;
use App\Services\Finance\InvoiceSummaryService;
use App\Support\DataTableFormatter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class InvoiceDataTable extends DataTable
{
    // Fix N+1: Cache exchange rates to avoid querying in the loop
    protected $exchangeRatesCache = null;

    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        // Fix N+1: Load all exchange rates once
        $this->exchangeRatesCache = \App\Models\ExchangeRate::query()
            ->whereIn('base_currency', ['USD', 'ARS', 'EUR'])
            ->whereIn('target_currency', ['USD', 'ARS', 'EUR'])
            ->latest('date')
            ->get()
            ->groupBy(function ($rate)
            {
                return $rate->base_currency.'_'.$rate->target_currency;
            });

        return (new EloquentDataTable($query))
            ->addColumn('action', 'invoices.action')
            ->setRowId('id')
            ->rawColumns(['status', 'action', 'enterprise_id', 'number_with_indicator', 'total_amount'])
            ->addColumn('number_with_indicator', function ($data)
            {
                // Punto de color según tipo de operación (rojo: compra, verde: venta)
                if ($data->operation == 'buy')
                {
                    $dot = '<span class="badge rounded-circle bg-danger" style="width:10px;height:10px;padding:0;display:inline-block;margin-right:8px;"></span>';
                } else
                {
                    $dot = '<span class="badge rounded-circle bg-success" style="width:10px;height:10px;padding:0;display:inline-block;margin-right:8px;"></span>';
                }

                $numberHtml = DataTableFormatter::showLink($data, 'invoice.show', $data->number, 'view', [$data->id], 'text-body');

                return $dot.$numberHtml;
            })
            ->editColumn('enterprise_id', function ($data)
            {
                if ($data->enterprise)
                {
                    return '<a href="'.e(route('client.show', $data->enterprise->id)).'" class="text-body">'.e($data->enterprise->name).'</a>';
                }

                $user = auth()->user();
                if ($user && $user->hasAnyRole(['admin', 'collaborator']))
                {
                    return '<a href="'.e(route('invoice.link-enterprise', $data->id)).'" class="text-body" title="'.e(__('invoice_enterprise.link.action_title')).'">'
                        .'<i class="ti ti-link ti-sm"></i>'
                        .'</a>';
                }

                return '<span class="text-muted">'.e(__('invoice_enterprise.no_enterprise')).'</span>';
            })
            ->filterColumn('enterprise_id', function ($query, $keyword)
            {
                $query->whereHas('enterprise', function ($q) use ($keyword)
                {
                    $q->whereRaw('name LIKE ?', ["%{$keyword}%"]);
                });
            })
            ->editColumn('date', function ($data)
            {
                return Carbon::parse($data->date)->format('d-m-Y');
            })
            ->editColumn('total_amount', function ($data)
            {
                $conversions = '';

                // Fix N+1: Use cached rates for conversion
                $ars = $this->convertToWithCache($data, 'ARS', 'total_amount');
                if ($ars !== null)
                {
                    $conversions .= '<span class="fw-bold">'.\App\Helpers\Helpers::formatMoney($ars, 'ARS').' ARS</span>';
                }

                $eur = $this->convertToWithCache($data, 'EUR', 'total_amount');
                if ($eur !== null)
                {
                    if ($conversions)
                    {
                        $conversions .= '<br>';
                    }
                    $conversions .= '<small class="text-muted">≈ '.\App\Helpers\Helpers::formatMoney($eur, 'EUR').' EUR</small>';
                }

                return $conversions ?: '<span class="text-muted">N/A</span>';
            })
            ->editColumn('status', function ($data)
            {
                return $data->status_badge;
            });
    }

    /**
     * Fix N+1: Convert using cached exchange rates
     */
    protected function convertToWithCache($invoice, string $targetCurrency, string $field = 'total_amount'): ?float
    {
        $baseCurrency = $invoice->currency ?? 'USD';
        $amount = $invoice->$field ?? 0;

        if ($baseCurrency === $targetCurrency)
        {
            return $amount;
        }

        $key = $baseCurrency.'_'.$targetCurrency;
        $rates = $this->exchangeRatesCache[$key] ?? collect();
        $rate = $rates->first();

        if ($rate)
        {
            return $amount * (float) $rate->rate;
        }

        // Try inverse conversion
        $inverseKey = $targetCurrency.'_'.$baseCurrency;
        $inverseRates = $this->exchangeRatesCache[$inverseKey] ?? collect();
        $inverseRate = $inverseRates->first();

        if ($inverseRate && $inverseRate->rate > 0)
        {
            return $amount / (float) $inverseRate->rate;
        }

        return null;
    }

    public function query(Invoice $model): QueryBuilder
    {
        $query = $model->newQuery()->with('enterprise');

        $summaryFilter = request()->input('summary_filter', 'all');
        if (in_array($summaryFilter, InvoiceSummaryService::SUMMARY_FILTERS, true))
        {
            app(InvoiceSummaryService::class)->applySummaryFilter($query, (string) $summaryFilter);
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        $initComplete = "function () {
            var api = this.api();
            window.invoiceSummaryFilter = window.invoiceSummaryFilter || 'all';

            function syncInvoiceSummaryFilterUi() {
                jQuery('.filter-invoice-summary').each(function () {
                    var el = jQuery(this);
                    el.toggleClass('active-filter', el.data('filter') === window.invoiceSummaryFilter);
                });
            }

            jQuery('.filter-invoice-summary').off('click.invoiceSummary').on('click.invoiceSummary', function (e) {
                e.preventDefault();
                var filter = jQuery(this).data('filter');
                window.invoiceSummaryFilter = window.invoiceSummaryFilter === filter ? 'all' : filter;
                syncInvoiceSummaryFilterUi();
                api.ajax.reload();
            });

            syncInvoiceSummaryFilterUi();
        }";

        return $this
            ->builder()
            ->setTableId('invoice-table')
            ->columns($this->getColumns())
            ->minifiedAjax(
                '',
                "data.summary_filter = window.invoiceSummaryFilter || 'all';",
            )
            ->dom('frtip')
            ->orderBy(2, 'desc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
            ->parameters([
                'select' => false,
                'autoWidth' => false,
                'initComplete' => $initComplete,
                'drawCallback' => 'function() {
					$("#invoice-table tbody tr").css({
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
            Column::computed('number_with_indicator')
                ->title('Comprobante')
                ->addClass('all')
                ->searchable(false)
                ->orderable(false),
            Column::make('date')
                ->title('Fecha')
                ->addClass('min-tablet')
                ->className('text-center'),
            Column::make('enterprise_id')
                ->title('Empresa')
                ->addClass('min-tablet')
                ->searchable(true)
                ->orderable(false),
            Column::make('total_amount')
                ->title('Total')
                ->addClass('min-desktop')
                ->className('text-end'),
            Column::make('discount')
                ->title('Descuento')
                ->addClass('none')
                ->className('text-end'),
            Column::make('balance')
                ->title('Saldo')
                ->addClass('min-desktop')
                ->className('text-end'),
            Column::make('status')
                ->title('Estado')
                ->addClass('min-phone')
                ->className('text-center'),
            Column::computed('action')
                ->title('Acciones')
                ->addClass('min-desktop')
                ->className('text-center')
                ->exportable(false)
                ->printable(false)
                ->width(60),
        ];
    }

    protected function filename(): string
    {
        return 'Invoice_'.date('YmdHis');
    }
}
