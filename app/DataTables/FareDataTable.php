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
            ->addColumn('units', function ($fare) {
                if ($fare->units->isEmpty()) {
                    return '<span class="text-muted">N/A</span>';
                }
                
                $badges = '';
                foreach ($fare->units as $unit) {
                    $badges .= '<span class="badge bg-label-primary me-1">' . $unit->type . '</span>';
                }
                return $badges;
            })
            ->addColumn('type', function ($fare) {
                return $fare->type ? $fare->type->name : '';
            })
            ->addColumn('glosary', function ($fare) {
                return $fare->glosary_id ? 'Texto explicando de qué trata este tipo de servicio / tarifa' : '';
            })
            ->orderColumn('type', function ($query, $order) {
                $query->leftJoin('fare_types', 'fares.type_id', '=', 'fare_types.id')
                      ->orderBy('fare_types.name', $order);
            })
            ->orderColumn('units', function ($query, $order) {
                // This is a simplified approach as units is a many-to-many relationship
                $query->orderBy('name', $order);
            })
            ->rawColumns(['action', 'units'])
            ->setRowId('id');
    }

    public function query(Fare $model): QueryBuilder
    {
        return $model->newQuery()->with(['units', 'type']);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('fares-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'asc')
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
            Column::make('name')->title('TARIFA')->searchable(true)->orderable(true),
            Column::computed('units')->title('UNIDADES')->searchable(false)->orderable(false),
            Column::computed('type')->title('TIPO')->searchable(true)->orderable(true),
            Column::computed('glosary')->title('GLOSARIO')->searchable(false)->orderable(false),
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