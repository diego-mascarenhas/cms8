<?php

namespace App\DataTables;

use App\Models\Communication;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use Carbon\Carbon;

class CommunicationDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'communication.action')
            ->setRowId('id')
            ->rawColumns(['status'])
            ->editColumn('user_id', function ($data) {
                return $data->user->name;
            })
            ->filterColumn('user_id', function ($query, $keyword) {
                $query->whereHas('user', function ($q) use ($keyword) {
                    $q->whereRaw("name LIKE ?", ["%{$keyword}%"]);
                });
            })
            ->editColumn('type_id', function ($data) {
                return $data->type->name;
            })
            ->editColumn('sent', function ($data)
            {
                return Carbon::parse($data->sent)->format('d-m-Y H:i:s');
            })
            ->editColumn('received', function ($data)
            {
                return Carbon::parse($data->received)->format('d-m-Y H:i:s');
            })
            ->editColumn('status', function ($data) {
                return $data->status_label;
            });
    }

    public function query(Communication $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('communication-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->dom('frtip')
                    ->orderBy(0);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('type_id')->title('Type')->orderable(false),
            Column::make('user_id')->title('User')->orderable(false),
            Column::make('reference')->title('Reference')->hidden(),
            Column::make('sent')->title('Sent'),
            Column::make('received')->title('Received'),
            Column::make('status')->title('Status')->className('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Communication_' . date('YmdHis');
    }
}