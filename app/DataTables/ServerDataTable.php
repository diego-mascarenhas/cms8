<?php

namespace App\DataTables;

use App\Models\Server;
use App\Support\DataTableFormatter;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ServerDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($server)
            {
                return view('server.action', ['id' => $server->id])->render();
            })
            ->setRowId('id')
            ->editColumn('name', function ($server)
            {
                return DataTableFormatter::showLink($server, 'server.show', $server->name, 'view', [$server->id]);
            })
            ->editColumn('status_id', function ($server)
            {
                $statusClass = $server->status_id->color();
                $statusText = $server->status_id->name();

                return '<div class="text-center"><span class="badge bg-label-'.$statusClass.'">'.$statusText.'</span></div>';
            })
            ->rawColumns(['name', 'status_id', 'action']);
    }

    public function query(): QueryBuilder
    {
        return Server::query();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('server-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->language([
                'url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json',
            ]);
    }

    protected function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')->title('Nombre'),
            Column::make('ip')->title('Dirección IP'),
            Column::make('server_url')->title('URL'),
            Column::make('username')->title('Usuario'),
            Column::make('status_id')->title('Estado')->addClass('text-center'),
            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->width(60)
                ->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Server_'.date('YmdHis');
    }
}
