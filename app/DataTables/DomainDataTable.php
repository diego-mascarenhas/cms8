<?php

namespace App\DataTables;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class DomainDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
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
            ->rawColumns(['suspended', 'action']);
    }

    public function query(): QueryBuilder
    {
        return Domain::query();
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
            ->processing(false)
            ->language(['url' => '/js/datatables/' . session()->get('locale', app()->getLocale()) . '.json']);
    }

    protected function getColumns(): array
    {
        return [
            Column::make('domain')->title('Domain'),
            Column::make('server_url')->title('Server'),
            Column::make('username')->title('Username'),
            Column::make('plan')->title('Plan'),
            Column::make('suspended')->title('Status'),
            Column::make('site_type')->title('Type'),
            Column::make('php_version')->title('PHP'),
        ];
    }

    protected function filename(): string
    {
        return 'Domain_' . date('YmdHis');
    }
} 