<?php

namespace App\DataTables;

use App\Models\Stylebook;
use App\Helpers\Helpers;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class StylebookDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        \Illuminate\Support\Facades\Log::debug('StylebookDataTable: dataTable method called');
        
        $dataTable = new EloquentDataTable($query);
        
        $dataTable->addColumn('action', function ($stylebook) {
            \Illuminate\Support\Facades\Log::debug('StylebookDataTable: Processing action for stylebook ID: ' . $stylebook->id);
            return view('stylebook.action', compact('stylebook'))->render();
        });
        
        $dataTable->editColumn('language', function ($row) {
            \Illuminate\Support\Facades\Log::debug('StylebookDataTable: Processing language for stylebook ID: ' . $row->id);
            $languageName = $row->languageRelation ? $row->languageRelation->name : strtoupper($row->language);
            
            // Use helper to map language code to appropriate country code for flags
            $countryCode = Helpers::getLanguageFlag($row->language);
            
            $flag = '<span class="fi fi-' . strtolower($countryCode) . ' me-2"></span>';
            return $flag . e($languageName);
        });
        
        $dataTable->editColumn('date', function ($row) {
            \Illuminate\Support\Facades\Log::debug('StylebookDataTable: Processing date for stylebook ID: ' . $row->id . ', date: ' . ($row->date ? $row->date->format('Y-m-d') : 'null'));
            return $row->date ? $row->date->format('d/m/Y') : '';
        });
        
        $dataTable->orderColumn('name', function ($query, $order) {
            $query->orderBy('name', $order);
        });
        
        $dataTable->orderColumn('language', function ($query, $order) {
            $query->orderBy('language', $order);
        });
        
        $dataTable->orderColumn('date', function ($query, $order) {
            $query->orderBy('date', $order);
        });
        
        $dataTable->rawColumns(['action', 'language'])
            ->setRowId('id');
            
        \Illuminate\Support\Facades\Log::debug('StylebookDataTable: dataTable method completed setup');
        
        return $dataTable;
    }

    public function query(Stylebook $model): QueryBuilder
    {
        // Global scope will handle team filtering automatically
        $query = $model->newQuery()->with('languageRelation');
        
        // Log the SQL query and bindings
        $rawSql = $query->toSql();
        $bindings = $query->getBindings();
        \Illuminate\Support\Facades\Log::debug('StylebookDataTable SQL: ' . $rawSql);
        \Illuminate\Support\Facades\Log::debug('StylebookDataTable Bindings: ' . json_encode($bindings));
        
        // Log the count and sample data
        $count = $query->count();
        \Illuminate\Support\Facades\Log::debug('StylebookDataTable Count: ' . $count);
        
        if ($count > 0) {
            // Log the first 2 records for debugging
            $sample = $query->limit(2)->get()->toArray();
            \Illuminate\Support\Facades\Log::debug('StylebookDataTable Sample: ' . json_encode($sample));
        }
        
        return $query;
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
            ->language(['url' => '/js/datatables/' . session()->get('locale', app()->getLocale()) . '.json'])
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
                ->addClass('text-center')
                ->title(__('Actions')),
        ];
    }

    protected function filename(): string
    {
        return 'Stylebook_'.date('YmdHis');
    }
} 