<?php

namespace App\DataTables;

use App\Models\Enterprise;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use Carbon\Carbon;

class SupplierDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'supplier.action')
            ->setRowId('id')
            ->addColumn('billing_address', function ($row) {
                $billingAddress = $row->enterpriseBillingAddress();
                return $billingAddress ? $billingAddress->name : null;
            })
            ->filterColumn('billing_address', function ($query, $keyword) {
                $query->whereHas('enterpriseBillingAddresses', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('status', function ($row) {
                return $row->status ? '<span class="badge rounded-pill bg-label-success">Active</span>' :
                                      '<span class="badge rounded-pill bg-label-warning">Inactive</span>';
            })
            ->rawColumns(['name', 'action', 'status'])
            ->editColumn('status', function ($data)
            {
                if ($data->status)
                {
                    return '<span class="badge rounded-pill bg-label-success">Active</span>';
                }
                else
                {
                    return '<span class="badge rounded-pill bg-label-warning">Inactive</span>';
                };
            });
    }

    public function query(Enterprise $model): QueryBuilder
    {
        return $model->suppliers()->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('supplier-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(2);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')->title('Name'),
            Column::make('billing_address')->title('Billing Address'),
            Column::make('status')->title('Status')->className('text-center'),
            Column::computed('action')->title('Actions')->width(20)->className('text-center')
                ->exportable(false)
                ->printable(false)
                ->width(30)
                ->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Supplier_' . date('YmdHis');
    }
}