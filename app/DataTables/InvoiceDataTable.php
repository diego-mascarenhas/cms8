<?php

namespace App\DataTables;

use App\Models\Invoice;
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

                return $dot.$data->number;
            })
            ->editColumn('enterprise_id', function ($data)
            {
                return $data->enterprise?->name ?? '<span class="text-muted">Sin empresa</span>';
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
                $currency = $data->currency ?? 'USD';
                $total = '$' . number_format($data->total_amount, 2);
                $conversions = '';

                // Mostrar conversiones a otras monedas
                if ($currency !== 'ARS') {
                    $ars = $data->convertTo('ARS', 'total_amount');
                    if ($ars) {
                        $conversions .= '<br><small class="text-muted">≈ $' . number_format($ars, 2) . ' ARS</small>';
                    }
                }

                if ($currency !== 'EUR') {
                    $eur = $data->convertTo('EUR', 'total_amount');
                    if ($eur) {
                        $conversions .= '<br><small class="text-muted">≈ €' . number_format($eur, 2) . ' EUR</small>';
                    }
                }

                return '<span class="fw-bold">' . $total . ' ' . $currency . '</span>' . $conversions;
            })
            ->editColumn('status', function ($data)
            {
                return $data->status_badge;
            });
    }

    public function query(Invoice $model): QueryBuilder
    {
        return $model->newQuery()->with('enterprise');
    }

    public function html(): HtmlBuilder
    {
        return $this
            ->builder()
            ->setTableId('invoice-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
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
