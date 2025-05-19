<?php

namespace App\DataTables;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class CollaboratorDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($contact) {
                return view('collaborator.action', compact('contact'));
            })
            ->addColumn('rating', function ($contact) {
                return '<div class="rating text-warning">
                    <i class="ti ti-star-filled"></i>
                    <i class="ti ti-star-filled"></i>
                    <i class="ti ti-star-filled"></i>
                    <i class="ti ti-star-filled"></i>
                    <i class="ti ti-star"></i>
                </div>';
            })
            ->addColumn('rates', function ($contact) {
                return '<a href="' . route('collaborator.rates', ['id' => $contact->id]) . '" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-tag me-1"></i>Ver tarifas
                </a>';
            })
            ->addColumn('projects', function ($contact) {
                return '<span class="badge bg-label-primary rounded-pill">' . rand(1, 10) . '</span>';
            })
            ->setRowId('id')
            ->filterColumn('categories', function($query, $keyword) {
                if ($keyword !== '') {
                    $query->whereHas('categories', function($q) use ($keyword) {
                        $q->where('id', $keyword);
                    });
                }
            })
            ->rawColumns(['name', 'action', 'rating', 'rates', 'projects']);
    }

    public function query(Contact $model): QueryBuilder
    {
        return $model->newQuery()->with([
        ]);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('collaborator-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('rtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/' . session()->get('locale', app()->getLocale()) . '.json'])
            ->parameters([
                'initComplete' => "function() {
                    var api = this.api();
                    api.columns('.select-filter').every(function() {
                        var column = this;
                        $('#CategoryFilter').on('change', function() {
                            let selectedValue = $(this).val();
                            api.column(3).search(selectedValue ? selectedValue : '', true, false).draw();
                        });
                    });
                }",
                'drawCallback' => "function() {
                    $('#CategoryFilter').off('change').on('change', function() {
                        let selectedValue = $(this).val();
                        $('#collaborator-table').DataTable().column(3).search(selectedValue ? selectedValue : '', true, false).draw();
                    });
                }",
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')
                ->title(__('Colaborador'))
                ->addClass('all'),
            Column::make('rating')
                ->title('Valoración')
                ->className('text-center')
                ->addClass('min-phone')
                ->searchable(false),
            Column::make('rates')
                ->title('Tarifas')
                ->className('text-center')
                ->addClass('min-desktop')
                ->searchable(false),
            Column::make('projects')
                ->title('Proyectos')
                ->className('text-center')
                ->addClass('min-desktop')
                ->searchable(false),
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
        return 'Collaborator_' . date('YmdHis');
    }
} 