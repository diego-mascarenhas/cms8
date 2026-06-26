<?php

namespace App\DataTables;

use App\Models\Domain;
use App\Support\DataTableFormatter;
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
            ->addColumn('action', function ($domain)
            {
                if (request()->route()->getName() == 'hosting.index')
                {
                    return view('hosting.action', ['id' => $domain->id])->render();
                }

                return view('domain.action', ['id' => $domain->id])->render();
            })
            ->setRowId('id')
            ->editColumn('domain', function ($domain)
            {
                $user = auth()->user();

                if ($user && ($user->can('domain.show') || $user->can('hosting.show')))
                {
                    return DataTableFormatter::link(
                        route('domain.show', $domain->id),
                        $domain->domain,
                    );
                }

                return '<span class="fw-medium text-body text-truncate">'.e($domain->domain).'</span>';
            })
            ->editColumn('suspended', function ($domain)
            {
                $statusClass = $domain->suspended ? 'danger' : 'success';
                $statusText = $domain->suspended ? 'Suspendido' : 'Activo';

                return '<div class="text-center"><span class="badge bg-label-'.$statusClass.'">'.$statusText.'</span></div>';
            })
            ->editColumn('site_type', function ($domain)
            {
                return $domain->site_type ?? '';
            })
            ->editColumn('php_version', function ($domain)
            {
                return $domain->php_version ?? '';
            })
            ->addColumn('server_url', function ($domain)
            {
                return $domain->server?->server_url ?? '';
            })
            ->rawColumns(['domain', 'suspended', 'action']);
    }

    public function query(): QueryBuilder
    {
        $query = Domain::with('server');

        if (auth()->check() && auth()->user()->currentTeam)
        {
            $teamId = auth()->user()->currentTeam->id;
            $query->whereHas('server', fn ($builder) => $builder->where('team_id', $teamId));
        }

        return $query;
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
                'url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json',
            ]);
    }

    protected function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('domain')->title('Dominio'),
            Column::make('username')->title('Usuario'),
            Column::computed('server_url')->title('Servidor'),
            Column::make('site_type')->title('Tipo'),
            Column::make('php_version')->title('PHP'),
            Column::make('suspended')->title('Estado')->addClass('text-center'),
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
        return 'Domain_'.date('YmdHis');
    }
}
