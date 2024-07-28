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
    protected $isDashboard = false;

    public function forDashboard($value = true)
    {
        $this->isDashboard = $value;
        return $this;
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'project.action')
            ->setRowId('id')
            ->editColumn('enterprise_id', function ($data) {
                return $data->client->name;
            })
            ->filterColumn('enterprise_id', function ($query, $keyword) {
                $query->whereHas('client', function ($q) use ($keyword) {
                    $q->whereRaw("name LIKE ?", ["%{$keyword}%"]);
                });
            })
            ->editColumn('category_id', function ($data) {
                return $data->category->name;
            })
            ->filterColumn('category_id', function ($query, $keyword) {
                $query->whereHas('category', function ($q) use ($keyword) {
                    $q->whereRaw("name LIKE ?", ["%{$keyword}%"]);
                });
            })
            ->editColumn('start_date', function ($data) {
                return Carbon::parse($data->start_date)->format('d-m-Y');
            })
            ->editColumn('end_date', function ($data) {
                return Carbon::parse($data->end_date)->format('d-m-Y');
            })
            ->editColumn('status', function ($data) {
                return $data->status_label;
            })
            ->rawColumns(['action', 'status']);
    }

    public function query(Project $model): QueryBuilder
    {
        $query = $model->newQuery();

        if ($this->isDashboard) {
            $query->where('status', 9)
                  ->orderBy('created_at', 'desc')
                  ->limit(5);
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        $builder = $this->builder()
                        ->setTableId('project-table')
                        ->columns($this->getColumns())
                        ->minifiedAjax()
                        ->responsive(true)
                        ->orderBy(0);

        if ($this->isDashboard) {
            $builder->dom('rtip'); // Hide the search box
            $builder->columns($this->getDashboardColumns());
        } else {
            $builder->dom('frtip'); // Show the search box
        }

        return $builder;
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->title('#')->responsivePriority(-1),
            Column::make('name')->title('Name'),
            Column::make('enterprise_id')->title('Client')->responsivePriority(1),
            Column::make('category_id')->title('Category')->responsivePriority(2),
            Column::make('start_date')->title('Start')->className('text-center')->responsivePriority(3),
            Column::make('end_date')->title('End')->className('text-center')->responsivePriority(4),
            Column::make('status')->title('Status')->className('text-center')->responsivePriority(5),
            Column::computed('action')->title('Action')
                  ->exportable(false)
                  ->printable(false)
                  ->width(60)
                  ->addClass('text-center')
                  ->responsivePriority(-1)
        ];
    }

    public function getDashboardColumns(): array
    {
        return [
            Column::make('name')->title('Name'),
            Column::make('start_date')->title('Start')->className('text-center')->responsivePriority(1),
            Column::make('end_date')->title('End')->className('text-center')->responsivePriority(2),
            Column::make('status')->title('Status')->className('text-center')->responsivePriority(3),
        ];
    }

    protected function filename(): string
    {
        return 'Project_' . date('YmdHis');
    }
}
