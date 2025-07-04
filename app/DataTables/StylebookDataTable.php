<?php

namespace App\DataTables;

use App\Helpers\Helpers;
use App\Models\Stylebook;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class StylebookDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($stylebook) {
                return view('stylebook.action', compact('stylebook'))->render();
            })
            ->editColumn('language', function ($row) {
                $languageName = $row->languageRelation ? $row->languageRelation->name : strtoupper($row->language);

                // Use helper to map language code to appropriate country code for flags
                $countryCode = Helpers::getLanguageFlag($row->language);

                $flag = '<span class="fi fi-'.strtolower($countryCode).' me-2"></span>';

                return $flag.e($languageName);
            })
            ->editColumn('date', function ($row) {
                return $row->date ? $row->date->format('d/m/Y') : '';
            })
            ->orderColumn('name', function ($query, $order) {
                $query->orderBy('name', $order);
            })
            ->orderColumn('language', function ($query, $order) {
                $query->orderBy('language', $order);
            })
            ->orderColumn('date', function ($query, $order) {
                $query->orderBy('date', $order);
            })
            ->rawColumns(['action', 'language'])
            ->setRowId('id');
    }

    public function query(Stylebook $model): QueryBuilder
    {
        // Global scope will handle team filtering automatically
        return $model->newQuery()->with('languageRelation');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('stylebook-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(0, 'asc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
            ->parameters([
                'select' => false,
                'lengthChange' => false,
            ]);
    }

    protected function getColumns(): array
    {
        return [
            Column::make('name')->title(__('Name'))->searchable(true)->orderable(true),
            Column::make('language')->title(__('Language'))->searchable(true)->orderable(true),
            Column::make('date')->title(__('Date'))->searchable(true)->orderable(true),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60)
                ->addClass('text-center')
                ->title(__('Actions')),
        ];
    }

    protected function filename(): string
    {
        return 'Stylebook_'.date('YmdHis');
    }
}
