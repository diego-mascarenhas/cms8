<?php

namespace App\DataTables;

use App\Models\Service;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ServiceDataTable extends DataTable
{
    // Fix N+1: Cache servers to avoid querying in the loop
    protected $serversCache = null;

    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        // Fix N+1: Load all servers once
        $this->serversCache = \App\Models\Server::pluck('name', 'id');

        $table = (new EloquentDataTable($query));

        // Add action column (blade view will handle policy-based permissions)
        $table = $table->addColumn('action', 'service.action');

        return $table
            ->setRowId('id')
            ->rawColumns(['name', 'action', 'status', 'operation_type'])
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
                return $data->client ? $data->client->name : 'N/A';
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
                return $data->serviceType->name;  // Fix N+1: Use eager-loaded serviceType
            })
            ->filterColumn('category_id', function ($query, $keyword)
            {
                $query->whereHas('serviceType', function ($q) use ($keyword)  // Fix N+1: Use serviceType relation
                {$q->whereRaw('name LIKE ?', ["%{$keyword}%"]);
                });
            })
            ->addColumn('domain', function ($data)
            {
                return $data->domain ?: '-';
            })
            ->addColumn('server', function ($data)
            {
                // Fix N+1: Use cache instead of individual queries
                if (! empty($data->data['server_id']))
                {
                    return $this->serversCache[$data->data['server_id']] ?? '-';
                }

                return '-';
            })
            ->filterColumn('server', function ($query, $keyword)
            {
                // Buscar servidores por nombre
                $serverIds = \App\Models\Server::where('name', 'LIKE', "%{$keyword}%")
                    ->pluck('id')
                    ->toArray();

                if (! empty($serverIds))
                {
                    $conditions = [];
                    foreach ($serverIds as $serverId)
                    {
                        $conditions[] = "JSON_EXTRACT(data, '$.server_id') = '{$serverId}'";
                    }
                    $query->whereRaw('('.implode(' OR ', $conditions).')');
                } else
                {
                    // Si no hay coincidencias, asegurar que no se devuelvan resultados
                    $query->whereRaw('1=0');
                }
            })
            ->filterColumn('domain', function ($query, $keyword)
            {
                $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, '$.domain'))) LIKE ?", ['%'.strtolower($keyword).'%']);
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
        $query = $model->newQuery()
            ->with([
                'client',
                'serviceType',  // Fix N+1: Use 'serviceType' instead of 'category' alias
                'currency',
            ])
            ->whereHas('client', function ($query)
            {
                $query->where('team_id', auth()->user()->currentTeam->id);
            });

        $user = Auth::user();
        if ($user && $user->hasRole('collaborator'))
        {
            $query->where('responsible_id', $user->id);
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('service-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(8, 'asc') // Ordenar por status
            ->pageLength(25);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::computed('operation_type')->title('')->width(5)->className('text-center'),
            Column::make('enterprise_id')->title('Client'),
            Column::make('category_id')->title('Category'),
            Column::make('domain')->title('Domain'),
            Column::make('server')->title('Server'),
            Column::make('calculated_price')->title('Price')->className('text-center'),
            Column::make('next_billing')->title('Next Billing')->className('text-center'),
            Column::make('status')->title('Status')->className('text-center'),
            Column::computed('action')
                ->title('Acciones')
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
