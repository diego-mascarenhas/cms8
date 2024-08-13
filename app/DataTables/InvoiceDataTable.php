<?php

namespace App\DataTables;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use Carbon\Carbon;

class InvoiceDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'invoice.action')
            ->setRowId('id')
            ->rawColumns(['status'])
            ->editColumn('enterprise_id', function ($data) {
                return $data->enterprise->name;
            })
            ->filterColumn('enterprise_id', function ($query, $keyword) {
                $query->whereHas('enterprise', function ($q) use ($keyword) {
                    $q->whereRaw("name LIKE ?", ["%{$keyword}%"]);
                });
            })
            ->editColumn('date', function ($data)
            {
                return Carbon::parse($data->date)->format('d-m-Y');
            })
            ->editColumn('status', function ($data) {
                return $data->status_label;
            });
    }

    public function query(Invoice $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('invoice-table')
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
            Column::make('enterprise_id')->title('Enterprise'),
            Column::make('operation')->title('Operation'),
            Column::make('total_amount')->title('Total'),
            Column::make('discount')->title('Discount'),
            Column::make('balance')->title('Balance'),
            Column::make('status')->title('Status')->className('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Invoice_' . date('YmdHis');
    }
}