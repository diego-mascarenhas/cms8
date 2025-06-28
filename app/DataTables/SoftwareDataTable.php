<?php

namespace App\DataTables;

use App\Models\Software;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SoftwareDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($software) {
                return view('software.action', compact('software'));
            })
            ->addColumn('type', function ($software) {
                return $software->type ? $software->type->name : '';
            })
            ->orderColumn('name', function ($query, $order) {
                $query->orderBy('name', $order);
            })
            ->orderColumn('type', function ($query, $order) {
                $query->leftJoin('software_types', 'software.type_id', '=', 'software_types.id')
                    ->orderBy('software_types.name', $order);
            })
            ->rawColumns(['action'])
            ->setRowId('id');
    }

    public function query(Software $model): QueryBuilder
    {
        // Global scope will handle team filtering automatically
        return $model->newQuery()->with(['type']);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('software-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(0, 'asc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/' . session()->get('locale', app()->getLocale()) . '.json'])
            ->parameters([
                'select' => false,
                'lengthChange' => false,
            ]);
    }

    protected function getColumns(): array
    {
        return [
            Column::make('name')->title('NOMBRE')->searchable(true)->orderable(true),
            Column::computed('type')->title('Categoría')->searchable(true)->orderable(true),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center')
                ->title('Acciones'),
        ];
    }

    protected function filename(): string
    {
        return 'Software_' . date('YmdHis');
    }
}
