<?php

namespace App\DataTables;

use App\Models\Fare;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class FareDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($fare) {
                return view('fare.action', compact('fare'));
            })
            ->addColumn('unit', function ($fare) {
                return $fare->unit ? $fare->unit->type : 'N/A';
            })
            ->addColumn('block', function ($fare) {
                return $fare->block ? $fare->block->name : 'N/A';
            })
            ->rawColumns(['action'])
            ->setRowId('id');
    }

    public function query(Fare $model): QueryBuilder
    {
        return $model->newQuery()->with(['unit', 'block']);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('fares-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('Bfrtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->language(['url' => '/js/datatables/' . session()->get('locale', app()->getLocale()) . '.json'])
            ->buttons([
                'copy', 'excel', 'pdf', 'print'
            ]);
    }

    protected function getColumns(): array
    {
        return [
            Column::make('id')->title('ID')->addClass('text-center'),
            Column::make('name')->title('Nombre')->searchable(true),
            Column::make('unit')->title('Unidad')->searchable(false),
            Column::make('block')->title('Bloque')->searchable(false),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center')
                ->title('Acciones'),
        ];
    }

    protected function filename(): string
    {
        return 'Fares_'.date('YmdHis');
    }
} 