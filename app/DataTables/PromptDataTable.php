<?php

namespace App\DataTables;

use App\Models\Prompt;
use App\Support\DataTableFormatter;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class PromptDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('section_label', function ($prompt)
            {
                return DataTableFormatter::showLink($prompt, 'prompt.show', $prompt->section_label, 'view', [$prompt]);
            })
            ->addColumn('action', function ($prompt)
            {
                return view('prompt.action', compact('prompt'));
            })
            ->editColumn('is_active', function ($prompt)
            {
                return $prompt->is_active
                    ? '<span class="badge rounded-pill bg-label-success">Activo</span>'
                    : '<span class="badge rounded-pill bg-label-secondary">Inactivo</span>';
            })
            ->addColumn('module_name', function ($prompt)
            {
                return $prompt->module
                    ? '<span class="badge bg-label-info">'.e($prompt->module->name).'</span>'
                    : '';
            })
            ->rawColumns(['section_label', 'action', 'is_active', 'module_name'])
            ->setRowId('id');
    }

    public function query(Prompt $model): QueryBuilder
    {
        return $model->newQuery()->with('module');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('prompt-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(4, 'asc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'select' => false,
            ]);
    }

    protected function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('section_label')->title(__('Sección'))->searchable(true)->orderable(true),
            Column::make('section_key')->title(__('Clave'))->searchable(true)->orderable(true),
            Column::computed('module_name')->title(__('Módulo'))->searchable(false)->orderable(false),
            Column::make('order')->title(__('Orden'))->orderable(true)->className('text-center'),
            Column::make('is_active')->title(__('Activo'))->orderable(true)->className('text-center'),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center')
                ->title(__('Acciones')),
        ];
    }

    protected function filename(): string
    {
        return 'Prompts_'.date('YmdHis');
    }
}
