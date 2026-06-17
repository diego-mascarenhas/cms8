<?php

namespace App\DataTables;

use App\Models\Service;
use App\Support\DataTableFormatter;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ServiceDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $table = (new EloquentDataTable($query));

        // Add action column (blade view will handle policy-based permissions)
        $table = $table->addColumn('action', 'service.action');

        return $table
            ->setRowId('id')
            ->rawColumns(['name', 'action', 'status', 'operation_type', 'enterprise_id'])
            ->addColumn('operation_type', function ($data)
            {
                if ($data->operation == 'buy')
                {
                    return '<span class="badge rounded-circle bg-danger" style="width:12px;height:12px;padding:0;display:inline-block;margin:0 auto;"></span>';
                } else
                {
                    return '<span class="badge rounded-circle bg-success" style="width:12px;height:12px;padding:0;display:inline-block;margin:0 auto;"></span>';
                }
            })
            ->editColumn('enterprise_id', function ($data)
            {
                if (! $data->client)
                {
                    return 'N/A';
                }

                return DataTableFormatter::showLink(
                    $data,
                    'service.show',
                    $data->client->name,
                    'view',
                    [$data->id],
                    'fw-medium text-body',
                );
            })
            ->filterColumn('enterprise_id', function ($query, $keyword)
            {
                $query->whereHas('client', function ($q) use ($keyword)
                {
                    $q->whereRaw('name LIKE ?', ["%{$keyword}%"]);
                });
            })
            ->editColumn('category_id', function ($data)
            {
                return $data->serviceType?->name ?? '—';
            })
            ->filterColumn('category_id', function ($query, $keyword)
            {
                $query->whereHas('serviceType', function ($q) use ($keyword)  // Fix N+1: Use serviceType relation
                {$q->whereRaw('name LIKE ?', ["%{$keyword}%"]);
                });
            })
            ->orderColumn('category_id', function ($query, $direction)
            {
                $query->orderBy('services.service_type_id', $direction);
            })
            ->filterColumn('metadata_search', function ($query, $keyword)
            {
                $driver = DB::getDriverName();

                if ($driver === 'pgsql')
                {
                    $query->whereRaw('CAST(services.data AS TEXT) ILIKE ?', ["%{$keyword}%"]);
                } elseif ($driver === 'sqlite')
                {
                    $query->whereRaw('CAST(services.data AS TEXT) LIKE ?', ["%{$keyword}%"]);
                } else
                {
                    $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(services.data, '$')) LIKE ?", ["%{$keyword}%"]);
                }
            })
            ->filterColumn('calculated_price', function ($query, $keyword)
            {
                $query->whereRaw('CAST(services.price AS TEXT) LIKE ?', ["%{$keyword}%"]);
            })
            ->filterColumn('description', function ($query, $keyword)
            {
                if (DB::getDriverName() === 'pgsql')
                {
                    $query->whereRaw('services.description ILIKE ?', ["%{$keyword}%"]);
                } else
                {
                    $query->whereRaw('services.description LIKE ?', ["%{$keyword}%"]);
                }
            })
            ->editColumn('next_billing', function ($data)
            {
                return $data->next_billing ? $data->next_billing->format('d-m-Y') : '-';
            })
            ->addColumn('calculated_price', function ($data)
            {
                $currencyCode = 'USD';

                if ($data->currency)
                {
                    $currencyCode = $data->currency->code ?? 'USD';
                }

                return $currencyCode.' '.number_format($data->calculated_price, 2, ',', '.');
            })
            ->editColumn('status', function ($data)
            {
                return $data->status_label;
            })
            ->orderColumn('status', function ($query, $direction)
            {
                // Custom ordering: 7, 5, 3, 2, 6, 8, 4, 1
                $orderMap = 'CASE
					WHEN status = 7 THEN 1
					WHEN status = 5 THEN 2
					WHEN status = 3 THEN 3
					WHEN status = 2 THEN 4
					WHEN status = 6 THEN 5
					WHEN status = 8 THEN 6
					WHEN status = 4 THEN 7
					WHEN status = 1 THEN 8
					ELSE 999 END';

                $query->orderByRaw("$orderMap $direction");
            });
    }

    public function query(Service $model): QueryBuilder
    {
        $user = Auth::user();

        if (! $user || ! $user->currentTeam)
        {
            return $model->newQuery()->whereRaw('1 = 0');
        }

        $driver = DB::getDriverName();

        $query = $model->newQuery()
            ->select('services.*')
            ->when(in_array($driver, ['pgsql', 'sqlite'], true), function ($q)
            {
                $q->selectRaw('CAST(services.data AS TEXT) as metadata_search');
            })
            ->when(! in_array($driver, ['pgsql', 'sqlite'], true), function ($q)
            {
                $q->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(services.data, '$')) as metadata_search");
            })
            ->with([
                'client',
                'serviceType',  // Fix N+1: Use 'serviceType' instead of 'category' alias
                'currency',
            ])
            ->whereHas('client', function ($query) use ($user)
            {
                $query->where('team_id', $user->currentTeam->id);
            });

        if ($user->hasRole('collaborator'))
        {
            $query->where('responsible_id', $user->id);
        }

        $statusFilter = request()->input('status_filter', '4');
        if ((string) $statusFilter !== 'all')
        {
            $query->where('services.status', (int) $statusFilter);
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        $statusLabel = e(__('Status'));
        $allLabel = e(__('All'));
        $initComplete = "function () {
    var api = this.api();
    var f = jQuery('#service-table_filter');
    if (! f.length) { return; }
    f.addClass('d-flex flex-wrap align-items-center justify-content-between column-gap-3 row-gap-2');
    if (! jQuery('#service-filter-status').length) {
        f.prepend(
            '<div class=\"d-inline-flex align-items-center flex-shrink-0\">' +
            '<label for=\"service-filter-status\" class=\"form-label mb-0 me-2 text-nowrap\">{$statusLabel}</label>' +
            '<select id=\"service-filter-status\" class=\"form-select form-select-sm\" style=\"min-width:12rem;max-width:14rem;\">' +
            '<option value=\"all\">{$allLabel}</option>' +
            '<option value=\"1\">Suspendido</option>' +
            '<option value=\"2\">Suspender</option>' +
            '<option value=\"3\">Activar</option>' +
            '<option value=\"4\" selected>Activo</option>' +
            '<option value=\"5\">Migrar</option>' +
            '<option value=\"6\">Migrando</option>' +
            '<option value=\"7\">Delegar</option>' +
            '<option value=\"8\">Analizar</option>' +
            '</select></div>'
        );
    }
    f.find('label').addClass('ms-auto mb-0');
    jQuery('#service-filter-status').off('change.serviceStatus').on('change.serviceStatus', function () {
        api.ajax.reload();
    });
}";

        return $this->builder()
            ->setTableId('service-table')
            ->columns($this->getColumns())
            ->minifiedAjax(
                '',
                "data.status_filter = ($('#service-filter-status').val() || '4');",
            )
            ->dom('frtip')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->orderBy(7, 'asc') // Ordenar por próxima facturación
            ->pageLength(25)
            ->parameters([
                'initComplete' => $initComplete,
                'drawCallback' => "function () {
                    var f = jQuery('#service-table_filter');
                    f.addClass('d-flex flex-wrap align-items-center justify-content-between column-gap-3 row-gap-2');
                    f.find('label').addClass('ms-auto mb-0');
                }",
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('metadata_search')->visible(false),
            Column::make('description')->visible(false),
            Column::computed('operation_type')->title('')->width(5)->className('text-center'),
            Column::make('enterprise_id')->title(__('Client')),
            Column::make('category_id')->title(__('Category')),
            Column::computed('calculated_price')->title(__('Price'))->className('text-center')->searchable(false)->orderable(false),
            Column::make('next_billing')->title(__('Próxima'))->className('text-center'),
            Column::make('status')->title(__('Status'))->className('text-center'),
            Column::computed('action')
                ->title(__('Actions'))
                ->width(20)
                ->className('text-center')
                ->addClass('min-desktop')
                ->exportable(false)
                ->printable(false)
                ->width(30),
        ];
    }

    protected function filename(): string
    {
        return 'Service_'.date('YmdHis');
    }
}
