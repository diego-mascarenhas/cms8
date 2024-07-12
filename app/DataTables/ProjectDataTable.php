<?php

namespace App\DataTables;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use Carbon\Carbon;

class ProjectDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'project.action')
            ->setRowId('id')
            ->rawColumns(['status'])
            ->editColumn('client_id', function ($data)
            {
                return $data->client->name;
            })
            ->filterColumn('client_id', function ($query, $keyword)
            {
                $query->whereHas('client', function ($q) use ($keyword) {
                    $q->whereRaw("name LIKE ?", ["%{$keyword}%"]);
                });
            })
            ->editColumn('type_id', function ($data)
            {
                return $data->type->name;
            })
            ->filterColumn('type_id', function ($query, $keyword)
            {
                $query->whereHas('type', function ($q) use ($keyword) {
                    $q->whereRaw("name LIKE ?", ["%{$keyword}%"]);
                });
            })
            ->editColumn('start_date', function ($data)
            {
                return Carbon::parse($data->start_date)->format('d-m-Y');
            })
            ->editColumn('end_date', function ($data)
            {
                return Carbon::parse($data->end_date)->format('d-m-Y');
            })
            ->editColumn('status', function ($data) {
                return $data->status_label;
            });
    }

    public function query(Project $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('project-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->dom('frtip')
                    ->orderBy(0);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->title('#'),
            Column::make('name')->title('Name'),
            Column::make('client_id')->title('Client'),
            Column::make('type_id')->title('Type'),
            Column::make('start_date')->title('Start')->className('text-center'),
            Column::make('end_date')->title('End')->className('text-center'),
            Column::make('status')->title('Status')->className('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Project_' . date('YmdHis');
    }
}