<?php

namespace App\DataTables;

use App\Models\Certification;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class CertificationDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($certification) {
                return view('certification.action', compact('certification'));
            })
            ->orderColumn('certification', function ($query, $order) {
                $query->orderBy('certification', $order);
            })
            ->orderColumn('language', function ($query, $order) {
                $query->orderBy('language', $order);
            })
            ->rawColumns(['action'])
            ->setRowId('id');
    }

    public function query(Certification $model): QueryBuilder
    {
        // Global scope will handle team filtering automatically
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('certification-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(0, 'asc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->language(['url' => '/js/datatables/' . session()->get('locale', app()->getLocale()) . '.json'])
            ->parameters([
                'select' => false,
                'lengthChange' => false,
            ]);
    }

    protected function getColumns(): array
    {
        return [
            Column::make('certification')->title('CERTIFICACIÓN')->searchable(true)->orderable(true),
            Column::make('language')->title('IDIOMA')->searchable(true)->orderable(true),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center')
                ->title('Acciones'),
        ];
    }

    protected function filename(): string
    {
        return 'Certification_'.date('YmdHis');
    }
} 