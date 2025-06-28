<?php

namespace App\DataTables;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class DomainDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($domain) {
                if (request()->route()->getName() == 'hosting.index') {
                    return view('hosting.action', ['id' => $domain->id])->render();
                }

                return view('domain.action', ['id' => $domain->id])->render();
            })
            ->setRowId('id')
            ->editColumn('suspended', function ($domain) {
                $statusClass = $domain->suspended ? 'danger' : 'success';
                $statusText = $domain->suspended ? 'Suspended' : 'Active';

                return '<span class="badge bg-label-' . $statusClass . '">' . $statusText . '</span>';
            })
            ->editColumn('site_type', function ($domain) {
                return $domain->site_type ?? 'N/A';
            })
            ->editColumn('php_version', function ($domain) {
                return $domain->php_version ?? 'N/A';
            })
            ->addColumn('server_url', function ($domain) {
                return $domain->server ? $domain->server->server_url : 'N/A';
            })
            ->rawColumns(['suspended', 'action']);
    }

    public function query(): QueryBuilder
    {
        return Domain::with('server');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('domain-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->language([
                'url' => '/js/datatables/' . session()->get('locale', app()->getLocale()) . '.json',
            ]);
    }

    protected function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('domain')->title('Domain'),
            Column::make('username')->title('Username'),
            Column::computed('server_url')->title('Server'),
            Column::make('site_type')->title('Type'),
            Column::make('php_version')->title('PHP'),
            Column::make('suspended')->title('Status'),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60)
                ->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Domain_' . date('YmdHis');
    }
}
