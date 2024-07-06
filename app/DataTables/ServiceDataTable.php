<?php

namespace App\DataTables;

use App\Models\Service;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use Carbon\Carbon;

class ServiceDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'service.action')
            ->setRowId('id')
            ->rawColumns(['name', 'status'])
            ->editColumn('client_id', function ($data)
            {
                return $data->client->name;
            })
            ->filterColumn('client_id', function ($query, $keyword) {
                $query->whereHas('client', function ($q) use ($keyword) {
                    $q->whereRaw("name LIKE ?", ["%{$keyword}%"]);
                });
            })
            ->editColumn('type_id', function ($data)
            {
                return $data->type->name;
            })
            ->filterColumn('type_id', function ($query, $keyword) {
                $query->whereHas('type', function ($q) use ($keyword) {
                    $q->whereRaw("name LIKE ?", ["%{$keyword}%"]);
                });
            })
            ->editColumn('created_at', function ($data)
            {
                return Carbon::parse($data->created_at)->format('d-m-Y');
            })
            ->editColumn('updated_at', function ($data)
            {
                return Carbon::parse($data->updated_at)->format('d-m-Y');
            })
            ->editColumn('status', function ($data) {
                return $data->status_label;
            });
    }

    public function query(Service $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('service-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->dom('frtip')
                    ->orderBy(0);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('client_id')->title('Client'),
            Column::make('type_id')->title('Type'),
            Column::make('created_at')->title('Created')->className('text-center'),
            Column::make('updated_at')->title('Updated')->className('text-center'),
            Column::make('status')->title('Status')->className('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Service_' . date('YmdHis');
    }
}