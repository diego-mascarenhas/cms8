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
            ->editColumn('language', function ($row) {
                $languageName = $row->languageRelation ? $row->languageRelation->name : strtoupper($row->language);
                
                // Mapeo especial para inglés - usa GB como bandera por defecto
                $countryCode = $row->language == 'en' ? 'gb' : $row->language;
                
                $flag = '<span class="fi fi-' . strtolower($countryCode) . ' me-2"></span>';
                return $flag . e($languageName);
            })
            ->orderColumn('certification', function ($query, $order) {
                $query->orderBy('certification', $order);
            })
            ->orderColumn('language', function ($query, $order) {
                $query->orderBy('language', $order);
            })
            ->rawColumns(['action', 'language'])
            ->setRowId('id');
    }

    public function query(Certification $model): QueryBuilder
    {
        // Global scope will handle team filtering automatically
        return $model->newQuery()->with('languageRelation');
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
            Column::make('certification')->title(__('Certification'))->searchable(true)->orderable(true),
            Column::make('language')->title(__('Language'))->searchable(true)->orderable(true),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center')
                ->title(__('Actions')),
        ];
    }

    protected function filename(): string
    {
        return 'Certification_'.date('YmdHis');
    }
} 