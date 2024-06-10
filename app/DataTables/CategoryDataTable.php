<?php

namespace App\DataTables;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class CategoryDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'category.action')
            ->setRowId('id')
            ->rawColumns(['name', 'action', 'status'])
            ->editColumn('created_at', function ($data)
            {
                $formated_date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $data->created_at)->format('d-m-Y');
                return $formated_date;
            })
            ->editColumn('updated_at', function ($data)
            {
                $formated_date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $data->updated_at)->format('d-m-Y');
                return $formated_date;
            })
            ->editColumn('status', function ($data)
            {
                if ($data->status)
                {
                    return '<span class="badge rounded-pill bg-label-success">Activa</span>';
                }
                else
                {
                    return '<span class="badge rounded-pill bg-label-warning">Inactiva</span>';
                };
            });
    }

    public function query(Category $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('category-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->dom('frtip')
                    ->orderBy(2);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')->title('Nombre'),
            Column::make('created_at')->title('Creada'),
            Column::make('updated_at')->title('Actualizada'),
            Column::make('status')->title('Estado'),
            Column::computed('action')->title('Acciones')
                  ->exportable(false)
                  ->printable(false)
                  ->width(30)
                  ->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Category_' . date('YmdHis');
    }
}