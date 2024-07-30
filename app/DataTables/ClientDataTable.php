<?php

namespace App\DataTables;

use App\Models\Enterprise;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use Carbon\Carbon;

class ClientDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'client.action')
            ->setRowId('id')
            ->addColumn('user_id', function ($row) {
                return $row->user ? $row->user->name : null;
            })
            ->filterColumn('user_id', function ($query, $keyword) {
                $query->whereHas('user', function ($q) use ($keyword) {
                    $q->whereRaw("name LIKE ?", ["%{$keyword}%"]);
                });
            })
            ->addColumn('assignee', function ($row) {
                return $row->assignee ? $row->assignee->name : null;
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
        $user = auth()->user();

        if ($user->can('client.list'))
        {
            return $model->clients()->newQuery();
        }
        elseif ($user->hasRole('colab'))
        {
            return $model->clients()->where('assigned_to', $user->id)->newQuery();
        }
        else
        {
            return $model->clients()->whereRaw('1 = 0')->newQuery();
        }
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('client-table')
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
            Column::make('user_id')->title('User'),
            Column::make('assigned_to')->title('Assigned'),
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
        return 'Client_' . date('YmdHis');
    }
}