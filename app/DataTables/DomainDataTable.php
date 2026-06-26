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
            ->addColumn('server_name', function ($domain)
            {
                return e($domain->server?->name ?? '');
            })
            ->filterColumn('server_name', function ($query, $keyword)
            {
                $query->whereHas('server', function ($builder) use ($keyword)
                {
                    $builder->where('name', 'like', '%'.$keyword.'%');
                });
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

        $serverFilter = request()->input('server_filter');
        if ($serverFilter !== null && $serverFilter !== '')
        {
            $query->where('server_id', (int) $serverFilter);
        }

        $statusFilter = request()->input('status_filter');
        if ($statusFilter === 'active')
        {
            $query->where('suspended', false);
        } elseif ($statusFilter === 'suspended')
        {
            $query->where('suspended', true);
        }

        $siteTypeFilter = request()->input('site_type_filter');
        if ($siteTypeFilter === '__none__')
        {
            $query->where(function (QueryBuilder $builder)
            {
                $builder->whereNull('site_type')
                    ->orWhere('site_type', '');
            });
        } elseif ($siteTypeFilter !== null && $siteTypeFilter !== '')
        {
            $query->where('site_type', $siteTypeFilter);
        }

        $phpVersionFilter = request()->input('php_version_filter');
        if ($phpVersionFilter === '__none__')
        {
            $query->where(function (QueryBuilder $builder)
            {
                $builder->whereNull('php_version')
                    ->orWhere('php_version', '');
            });
        } elseif ($phpVersionFilter !== null && $phpVersionFilter !== '')
        {
            $query->where('php_version', $phpVersionFilter);
        }

        return $query;
    }

    private function teamScopedDomainsQuery(): QueryBuilder
    {
        $query = Domain::query();

        if (auth()->check() && auth()->user()->currentTeam)
        {
            $query->whereHas('server', fn (QueryBuilder $builder) => $builder->where('team_id', auth()->user()->currentTeam->id));
        }

        return $query;
    }

    private function buildSiteTypeFilterOptions(): string
    {
        $typesQuery = $this->teamScopedDomainsQuery()
            ->select('site_type')
            ->whereNotNull('site_type')
            ->where('site_type', '!=', '')
            ->distinct();

        $types = collect(['WordPress', 'Laravel', 'Static', 'Other'])
            ->merge($typesQuery->orderBy('site_type')->pluck('site_type'))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $options = '<option value="">Todos</option>';

        foreach ($types as $type)
        {
            $options .= '<option value="'.e($type).'">'.e($type).'</option>';
        }

        $options .= '<option value="__none__">Sin tipo</option>';

        return $options;
    }

    private function buildPhpVersionFilterOptions(): string
    {
        $versions = $this->teamScopedDomainsQuery()
            ->select('php_version')
            ->whereNotNull('php_version')
            ->where('php_version', '!=', '')
            ->distinct()
            ->pluck('php_version')
            ->filter()
            ->sort(function (string $left, string $right): int
            {
                return version_compare($this->normalizePhpVersionForSort($left), $this->normalizePhpVersionForSort($right));
            })
            ->values();

        $options = '<option value="">Todos</option>';

        foreach ($versions as $version)
        {
            $options .= '<option value="'.e($version).'">'.e($version).'</option>';
        }

        $options .= '<option value="__none__">Sin PHP</option>';

        return $options;
    }

    private function normalizePhpVersionForSort(string $version): string
    {
        if (preg_match('/(\d+\.\d+)/', $version, $matches))
        {
            return $matches[1];
        }

        return $version;
    }

    public function html(): HtmlBuilder
    {
        $serverOptions = '<option value="">Todos</option>';
        $serversQuery = Server::query()->orderBy('name');

        if (auth()->check() && auth()->user()->currentTeam)
        {
            $serversQuery->where('team_id', auth()->user()->currentTeam->id);
        }

        foreach ($serversQuery->get(['id', 'name']) as $server)
        {
            $serverOptions .= '<option value="'.e((string) $server->id).'">'.e($server->name).'</option>';
        }

        $siteTypeOptions = $this->buildSiteTypeFilterOptions();
        $phpVersionOptions = $this->buildPhpVersionFilterOptions();

        $initComplete = "function () {
    var api = this.api();
    var f = jQuery('#domain-table_filter');
    if (! f.length) { return; }
    f.addClass('d-flex flex-nowrap align-items-center gap-2');
    if (! jQuery('#domain-filter-server').length) {
        f.prepend(
            '<div id=\"domain-table-filters\" class=\"d-flex flex-nowrap align-items-center gap-2 flex-shrink-1\">' +
            '<div class=\"d-inline-flex align-items-center flex-shrink-0\">' +
            '<label for=\"domain-filter-server\" class=\"form-label mb-0 me-1 text-nowrap small\">Servidor</label>' +
            '<select id=\"domain-filter-server\" class=\"form-select form-select-sm\" style=\"width:9rem;\">{$serverOptions}</select>' +
            '</div>' +
            '<div class=\"d-inline-flex align-items-center flex-shrink-0\">' +
            '<label for=\"domain-filter-status\" class=\"form-label mb-0 me-1 text-nowrap small\">Estado</label>' +
            '<select id=\"domain-filter-status\" class=\"form-select form-select-sm\" style=\"width:7.5rem;\">' +
            '<option value=\"\">Todos</option>' +
            '<option value=\"active\">Activo</option>' +
            '<option value=\"suspended\">Suspendido</option>' +
            '</select></div>' +
            '<div class=\"d-inline-flex align-items-center flex-shrink-0\">' +
            '<label for=\"domain-filter-site-type\" class=\"form-label mb-0 me-1 text-nowrap small\">Tipo</label>' +
            '<select id=\"domain-filter-site-type\" class=\"form-select form-select-sm\" style=\"width:8rem;\">{$siteTypeOptions}</select>' +
            '</div>' +
            '<div class=\"d-inline-flex align-items-center flex-shrink-0\">' +
            '<label for=\"domain-filter-php-version\" class=\"form-label mb-0 me-1 text-nowrap small\">PHP</label>' +
            '<select id=\"domain-filter-php-version\" class=\"form-select form-select-sm\" style=\"width:6rem;\">{$phpVersionOptions}</select>' +
            '</div></div>'
        );
    }
    f.find('input[type=\"search\"]').closest('label').addClass('ms-auto mb-0 flex-shrink-0 text-nowrap');
    jQuery('#domain-filter-server, #domain-filter-status, #domain-filter-site-type, #domain-filter-php-version').off('change.domainFilters').on('change.domainFilters', function () {
        api.ajax.reload();
    });
}";

        return $this->builder()
            ->setTableId('domain-table')
            ->columns($this->getColumns())
            ->minifiedAjax(
                '',
                "data.server_filter = ($('#domain-filter-server').val() || ''); data.status_filter = ($('#domain-filter-status').val() || ''); data.site_type_filter = ($('#domain-filter-site-type').val() || ''); data.php_version_filter = ($('#domain-filter-php-version').val() || '');",
            )
            ->dom('frtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->parameters([
                'initComplete' => $initComplete,
                'drawCallback' => "function () {
                    var f = jQuery('#domain-table_filter');
                    f.addClass('d-flex flex-nowrap align-items-center gap-2');
                    f.find('input[type=\"search\"]').closest('label').addClass('ms-auto mb-0 flex-shrink-0 text-nowrap');
                }",
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
            Column::computed('server_name')->title('Servidor'),
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
