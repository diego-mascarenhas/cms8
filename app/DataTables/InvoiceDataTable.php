<?php

namespace App\DataTables;

use App\Models\Invoice;
use App\Services\Finance\InvoiceSummaryService;
use App\Support\DataTableFormatter;
use App\Support\InvoiceTableAmountFormatter;
use App\Support\SearchNormalizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class InvoiceDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function (Invoice $data)
            {
                return view('invoices.action', [
                    'id' => $data->id,
                    'enterprise' => $data->enterprise,
                ])->render();
            })
            ->setRowId('id')
            ->rawColumns(['status', 'action', 'enterprise_id', 'number_with_indicator', 'total_amount', 'balance'])
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
            ->filterColumn('number_with_indicator', function ($query, $keyword): void
            {
                $keyword = trim((string) $keyword);

                if ($keyword === '')
                {
                    return;
                }

                $query->where('invoices.number', 'like', '%'.$keyword.'%');
            })
            ->filterColumn('enterprise_id', function ($query, $keyword): void
            {
                $keyword = trim((string) $keyword);

                if ($keyword === '')
                {
                    return;
                }

                $query->whereHas('enterprise', function ($enterpriseQuery) use ($keyword): void
                {
                    SearchNormalizer::applyEnterpriseNavbarConditions($enterpriseQuery, $keyword);
                });
            })
            ->filterColumn('date', function ($query, $keyword): void
            {
                $keyword = trim((string) $keyword);

                if ($keyword === '')
                {
                    return;
                }

                $query->where(function ($dateQuery) use ($keyword): void
                {
                    $dateQuery->where('invoices.date', 'like', '%'.$keyword.'%');

                    foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $format)
                    {
                        try
                        {
                            $parsed = Carbon::createFromFormat($format, $keyword);

                            if ($parsed !== false)
                            {
                                $dateQuery->orWhereDate('invoices.date', $parsed->toDateString());
                            }
                        } catch (\Throwable)
                        {
                        }
                    }
                });
            })
            ->editColumn('date', function ($data)
            {
                return Carbon::parse($data->date)->format('d-m-Y');
            })
            ->editColumn('total_amount', function ($data)
            {
                return InvoiceTableAmountFormatter::formatNative(
                    (float) ($data->total_amount ?? 0),
                    $data->currency_code,
                );
            })
            ->editColumn('balance', function ($data)
            {
                return InvoiceTableAmountFormatter::formatNative(
                    (float) ($data->balance ?? 0),
                    $data->currency_code,
                );
            })
            ->editColumn('status', function ($data)
            {
                return $data->status_badge;
            });
    }

    public function query(Invoice $model): QueryBuilder
    {
        $query = $model->newQuery()->with(['enterprise', 'currency']);

        $summaryService = app(InvoiceSummaryService::class);
        $summaryFilter = $summaryService->resolveListFilter(request()->input('summary_filter'));
        $summaryService->applySummaryFilter($query, $summaryFilter);

        return $query;
    }

    public function html(): HtmlBuilder
    {
        $initComplete = "function () {
            var api = this.api();
            window.invoiceSummaryFilter = window.invoiceSummaryFilter || '".InvoiceSummaryService::DEFAULT_LIST_FILTER."';

            function syncInvoiceSummaryFilterUi() {
                jQuery('.filter-invoice-summary').each(function () {
                    var el = jQuery(this);
                    el.toggleClass('active-filter', el.data('filter') === window.invoiceSummaryFilter);
                });
            }

            jQuery('.filter-invoice-summary').off('click.invoiceSummary').on('click.invoiceSummary', function (e) {
                e.preventDefault();
                var filter = jQuery(this).data('filter');
                window.invoiceSummaryFilter = window.invoiceSummaryFilter === filter
                    ? '".InvoiceSummaryService::DEFAULT_LIST_FILTER."'
                    : filter;
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
                "data.summary_filter = window.invoiceSummaryFilter || '".InvoiceSummaryService::DEFAULT_LIST_FILTER."';",
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
                ->searchable(true)
                ->orderable(false),
            Column::make('date')
                ->title('Fecha')
                ->addClass('min-tablet')
                ->className('text-center')
                ->searchable(true),
            Column::make('enterprise_id')
                ->title('Empresa')
                ->addClass('min-tablet')
                ->searchable(true)
                ->orderable(false),
            Column::make('total_amount')
                ->title('Total')
                ->addClass('min-desktop')
                ->className('text-end')
                ->searchable(false),
            Column::make('balance')
                ->title('Saldo')
                ->addClass('min-desktop')
                ->className('text-end')
                ->searchable(false),
            Column::make('status')
                ->title('Estado')
                ->addClass('min-phone')
                ->className('text-center')
                ->searchable(false),
            Column::computed('action')
                ->title('Acciones')
                ->addClass('all')
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
