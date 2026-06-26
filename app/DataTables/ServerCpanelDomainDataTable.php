<?php

namespace App\DataTables;

use App\Models\Domain;
use App\Models\Server;
use App\Support\DataTableFormatter;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ServerCpanelDomainDataTable extends DataTable
{
    protected ?Server $server = null;

    public function forServer(Server $server): self
    {
        $this->server = $server;

        return $this;
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->setRowId('id')
            ->editColumn('domain', function (Domain $domain)
            {
                $user = auth()->user();

                if ($user && ($user->can('domain.show') || $user->can('hosting.show')))
                {
                    return DataTableFormatter::link(
                        route('domain.show', $domain->id),
                        $domain->domain,
                    );
                }

                return '<span class="fw-medium text-body">'.e($domain->domain).'</span>';
            })
            ->editColumn('plan', fn (Domain $domain) => $domain->plan ?: 'N/D')
            ->editColumn('suspended', function (Domain $domain)
            {
                $statusClass = $domain->suspended ? 'danger' : 'success';
                $statusText = $domain->suspended ? 'Suspendido' : 'Activo';

                return '<div class="text-center"><span class="badge bg-label-'.$statusClass.'">'.$statusText.'</span></div>';
            })
            ->addColumn('disk_usage', function (Domain $domain)
            {
                return '<div class="text-end">'.$this->formatDiskUsage($domain).'</div>';
            })
            ->rawColumns(['domain', 'suspended', 'disk_usage']);
    }

    public function query(): QueryBuilder
    {
        /** @var Server $server */
        $server = $this->server ?? request()->route('server');

        return Domain::query()
            ->where('server_id', $server->id);
    }

    public function html(): HtmlBuilder
    {
        /** @var Server $server */
        $server = $this->server ?? request()->route('server');

        return $this->builder()
            ->setTableId('server-cpanel-domains-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('server.show', $server->id))
            ->dom('frtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->parameters([
                'select' => false,
                'autoWidth' => false,
                'drawCallback' => 'function() {
                    $("#server-cpanel-domains-table tbody tr").css({
                        "user-select": "none",
                        "-webkit-user-select": "none",
                        "-moz-user-select": "none",
                        "-ms-user-select": "none"
                    });
                }',
            ])
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
            Column::make('plan')->title('Plan'),
            Column::make('suspended')->title('Estado')->addClass('text-center'),
            Column::computed('disk_usage')
                ->title('Disco usado')
                ->addClass('text-end')
                ->orderable(false)
                ->searchable(false),
        ];
    }

    protected function filename(): string
    {
        return 'ServerCpanelDomains_'.date('YmdHis');
    }

    private function formatDiskUsage(Domain $domain): string
    {
        $data = $domain->data ?? [];
        $used = $data['disk_used'] ?? $data['diskused'] ?? null;
        $limit = $data['disk_limit'] ?? $data['disklimit'] ?? null;

        if (($used === null || $used === '') && ($limit === null || $limit === ''))
        {
            return '';
        }

        $usedLabel = $this->formatDiskValue($used);

        if ($limit === null || $limit === '' || $limit === 'unlimited' || $limit === 0)
        {
            if ($limit === 'unlimited' || $limit === 0)
            {
                return $usedLabel !== '' ? $usedLabel.' / unlimited' : 'unlimited';
            }

            return $usedLabel;
        }

        return trim($usedLabel.' / '.$this->formatDiskValue($limit));
    }

    private function formatDiskValue(mixed $value): string
    {
        if ($value === null || $value === '')
        {
            return '';
        }

        if (is_numeric($value))
        {
            return $value.' MB';
        }

        return (string) $value;
    }
}
