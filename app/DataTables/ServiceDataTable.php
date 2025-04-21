<?php

namespace App\DataTables;

use App\Models\Service;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use Carbon\Carbon;

class ServiceDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'service.action')
            ->setRowId('id')
            ->rawColumns(['name', 'action', 'status', 'operation_type'])
            ->addColumn('operation_type', function ($data)
            {
                if ($data->operation == 'buy')
                {
                    return '<span class="badge rounded-circle bg-danger" style="width:12px;height:12px;padding:0;display:inline-block;margin:0 auto;"></span>';
                }
                else
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
                    $q->whereRaw("name LIKE ?", ["%{$keyword}%"]);
                });
            })
            ->editColumn('category_id', function ($data)
            {
                return $data->category->name;
            })
            ->filterColumn('category_id', function ($query, $keyword)
            {
                $query->whereHas('category', function ($q) use ($keyword)
                {
                    $q->whereRaw("name LIKE ?", ["%{$keyword}%"]);
                });
            })
            ->editColumn('created_at', function ($data)
            {
                return Carbon::parse($data->created_at)->format('d-m-Y');
            })
            ->editColumn('updated_at', function ($data)
            {
                return Carbon::parse($data->updated_at)->format('d-m-Y');
            })
            ->addColumn('calculated_price', function ($data)
            {
                return number_format($data->calculated_price, 2, ',', '.');
            })
            ->editColumn('status', function ($data)
            {
                return $data->status_label;
            });
    }

    public function query(Service $model): QueryBuilder
    {
        return $model->newQuery()->whereHas('client', function ($query)
        {
            $query->where('team_id', auth()->user()->currentTeam->id);
        });
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('service-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(0);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::computed('operation_type')->title('')->width(5)->className('text-center'),
            Column::make('enterprise_id')->title('Client'),
            Column::make('category_id')->title('Category'),
            Column::make('calculated_price')->title('Price')->className('text-center'),
            Column::make('created_at')->title('Created')->className('text-center'),
            Column::make('updated_at')->title('Updated')->className('text-center'),
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
        return 'Service_' . date('YmdHis');
    }
}