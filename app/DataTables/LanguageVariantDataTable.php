<?php

namespace App\DataTables;

use App\Models\LanguageVariant;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class LanguageVariantDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($variant) {
                return view('language.variants.action', compact('variant'));
            })
            ->setRowId('id')
            ->editColumn('name', function ($row) {
                $flag = $row->country_code ? '<span class="fi fi-' . strtolower($row->country_code) . ' me-2"></span>' : '';
                return $flag . e($row->name);
            })
            ->editColumn('base_language', function ($row) {
                return $row->baseLanguage ? e($row->baseLanguage->name) : e($row->base_language);
            })
            ->editColumn('country_code', function ($row) {
                return strtoupper($row->country_code ?? '');
            })
            ->rawColumns(['name', 'action']);
    }

    public function query(LanguageVariant $model): QueryBuilder
    {
        return $model->newQuery()->with(['baseLanguage']);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('language-variant-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/' . session()->get('locale', app()->getLocale()) . '.json'])
            ->parameters([
                'drawCallback' => "function() {
                    // Add any specific callback functionality here
                }",
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('code')
                ->title(__('Code'))
                ->addClass('all'),
            Column::make('name')
                ->title(__('Name'))
                ->addClass('all'),
            Column::make('base_language')
                ->title(__('Language'))
                ->addClass('min-tablet'),
            Column::make('country_code')
                ->title(__('Country'))
                ->addClass('min-tablet'),
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
        return 'LanguageVariant_' . date('YmdHis');
    }
} 