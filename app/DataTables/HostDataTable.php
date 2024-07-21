<?php

namespace App\DataTables;

use App\Models\Host;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use Carbon\Carbon;

class HostDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'host.action')
            ->setRowId('id')
            ->rawColumns(['name', 'power_state', 'action'])
            ->editColumn('type_id', function ($data)
            {
                return $data->type->name;
            })
            ->editColumn('power_state', function ($data)
            {
                if ($data->power_state == 'POWERED_ON')
                {
                    return '<div class="ms-3 badge bg-label-success">ON</div>';
                }
                else
                {
                    return '<div class="ms-3 badge bg-label-danger">OFF</div>';
                }
            });
    }

    public function query(Host $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('host-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'asc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')->title('Name'),
            Column::make('type_id')->title('Type'),
            Column::make('private_ip')->title('Private IP'),
            Column::make('public_ip')->title('Public IP'),
            Column::make('power_state')->title('State')->className('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Host_' . date('YmdHis');
    }
}