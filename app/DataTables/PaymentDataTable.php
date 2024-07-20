<?php

namespace App\DataTables;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use Carbon\Carbon;

class PaymentDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'payment.action')
            ->setRowId('id')
            ->rawColumns(['status'])
            ->editColumn('date', function ($data)
            {
                return Carbon::parse($data->date)->format('d-m-Y');
            })
            ->editColumn('status', function ($data) {
                return $data->status_label;
            });
    }

    public function query(Payment $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('payment-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->dom('frtip')
                    ->orderBy(0);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('number')->title('Number'),
            Column::make('date')->title('Date'),
            Column::make('operation')->title('Operation'),
            Column::make('total_amount')->title('Total'),
            Column::make('discount')->title('Discount'),
            Column::make('balance')->title('Balance'),
            Column::make('status')->title('Status')->className('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Payment_' . date('YmdHis');
    }
}