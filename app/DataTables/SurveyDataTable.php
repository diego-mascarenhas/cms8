<?php

namespace App\DataTables;

use App\Models\Survey;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SurveyDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('title', function (Survey $survey)
            {
                return '<a href="'.route('survey.show', $survey).'">'.e($survey->title).'</a>';
            })
            ->editColumn('expires_at', function (Survey $survey)
            {
                return $survey->expires_at?->format('d/m/Y') ?? '';
            })
            ->editColumn('is_active', function (Survey $survey)
            {
                return $survey->is_active ? __('Activa') : __('Inactiva');
            })
            ->addColumn('action', function (Survey $survey)
            {
                return view('survey.action', compact('survey'));
            })
            ->rawColumns(['title', 'action'])
            ->setRowId('id');
    }

    public function query(Survey $model): QueryBuilder
    {
        return $model->newQuery()->withCount('responses');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('survey-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(0, 'desc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'select' => false,
                'lengthChange' => false,
            ]);
    }

    /**
     * @return list<Column>
     */
    protected function getColumns(): array
    {
        return [
            Column::make('title')->title(__('Título'))->searchable(true)->orderable(true),
            Column::make('code')->title(__('Código'))->searchable(true)->orderable(true),
            Column::make('expires_at')->title(__('Vencimiento'))->searchable(false)->orderable(true),
            Column::make('is_active')->title(__('Estado'))->searchable(false)->orderable(true),
            Column::make('responses_count')->title(__('Respuestas'))->searchable(false)->orderable(true),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center')
                ->title(__('Acciones')),
        ];
    }
}
