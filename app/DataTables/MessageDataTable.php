<?php

namespace App\DataTables;

use App\Models\Message;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use Carbon\Carbon;

class MessageDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'message.action')
            ->setRowId('id')
            ->rawColumns(['name', 'action', 'status'])
            ->editColumn('type_id', function ($data)
            {
                return $data->type->name;
            })
            ->editColumn('category_id', function ($data)
            {
                return optional($data->category)->name;
            })
            ->editColumn('updated_at', function ($data)
            {
                return Carbon::parse($data->updated_at)->format('d-m-Y H:i:s');
            })
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

    public function query(Message $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('message-table')
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
            Column::make('type_id')->title('Type'),
            Column::make('category_id')->title('Category'),
            Column::make('updated_at')->title('Updated')->className('text-center'),
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
        return 'Message_' . date('YmdHis');
    }
}