<?php

namespace App\DataTables;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Carbon\Carbon;

class ProjectDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'project.action')
            ->setRowId('id')
            ->editColumn('enterprise_id', function ($data) {
                return $data->client->name;
            })
            ->filterColumn('enterprise_id', function ($query, $keyword) {
                $query->whereHas('client', function ($q) use ($keyword) {
                    $q->whereRaw("name LIKE ?", ["%{$keyword}%"]);
                });
            })
            ->editColumn('category_id', function ($data) {
                return $data->category ? $data->category->name : 'Sin categoría';
            })
            ->filterColumn('category_id', function ($query, $keyword) {
                $query->whereHas('category', function ($q) use ($keyword) {
                    $q->whereRaw("name LIKE ?", ["%{$keyword}%"]);
                });
            })
            ->editColumn('start_date', function ($data) {
                return Carbon::parse($data->start_date)->format('d-m-Y');
            })
            ->editColumn('end_date', function ($data) {
                return Carbon::parse($data->end_date)->format('d-m-Y');
            })
            ->addColumn('responsible_name', function ($contact) {
                return $contact->responsible->name ?? 'Sin asignar';
            })
            ->filterColumn('responsible_name', function($query, $keyword) {
                $query->whereHas('responsible', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            })
            ->editColumn('status_id', function ($row) {
                return $row->status_label;
            })
            ->rawColumns(['action', 'status_id']);
    }

    public function query(Project $model): QueryBuilder
    {
        return $model->newQuery()->with([
            'client',
            'responsible:id,name',
        ]);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('project-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/' . session()->get('locale', app()->getLocale()) . '.json'])
            ->parameters([
                'initComplete' => "function() {
                    var api = this.api();
                    api.columns('.select-filter').every(function() {
                        var column = this;
                        $('#EmotionalState').on('change', function() {
                            var val = $.fn.dataTable.util.escapeRegex($(this).val());
                            column.search(val ? val : '', true, false).draw();
                        });

                        $('.filter-status').on('click', function(e) {
                            e.preventDefault();
                            var status = $(this).data('status');
                            api.column('status_id:name').search(status).draw();
                        });
                    });
                }",
                'drawCallback' => "function() {
                    $('#EmotionalState').off('change').on('change', function() {
                        $('#contact-table').DataTable().columns('.select-filter').search($(this).val()).draw();
                    });
                }",
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')
                ->title('Nombre')
                ->addClass('all'),
            Column::make('enterprise_id')
                ->title('Client')
                ->addClass('min-tablet')
                ->searchable(true)
                ->orderable(false),
            Column::make('category_id')
                ->title('Category')
                ->className('text-center')
                ->addClass('min-phone')
                ->searchable(true)
                ->orderable(false)
                ->width(150),
            Column::make('responsible_name')
                ->title('Responsable')
                ->className('text-center')
                ->addClass('min-desktop')
                ->searchable(false)
                ->orderable(false),
            Column::make('end_date')
                ->title('Entrega')
                ->className('text-center')
                ->addClass('min-desktop')
                ->searchable(false)
                ->orderable(false),
            Column::make('status_id')
                ->title('Estado')
                ->className('text-center')
                ->addClass('min-tablet'),
            Column::computed('action')
                ->title('Acciones')
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
        return 'Project_' . date('YmdHis');
    }
}
