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
            ->setRowId('id')
            ->editColumn('name', function ($row) {
                $companyName = $row->enterprise ? e($row->enterprise->name) : '';

                return '<div class="d-flex flex-column">
                            <span class="fw-medium text-body text-truncate">' . e($row->name) . '</span>
                            <small class="text-muted">' . ($companyName ?: '&nbsp;') . '</small>
                        </div>';
            })
            ->addColumn('sources', function ($row) {
                return $row->sources_icons_html;
            })
            ->addColumn('responsible_name', function ($contact) {
                return $contact->responsible->name ?? __('Unassigned');
            })
            ->filterColumn('responsible_name', function($query, $keyword) {
                $query->whereHas('responsible', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('categories', function ($row) {
                return $row->categories->map(function($category) {
                    return '<span class="badge bg-label-primary me-1">' . e($category->name) . '</span>';
                })->join(' ');
            })
            ->filterColumn('categories', function($query, $keyword) {
                if ($keyword !== '') {
                    $query->whereHas('categories', function($q) use ($keyword) {
                        $q->where('id', $keyword);
                    });
                }
            })
            ->rawColumns(['name', 'action', 'sources', 'categories']);
    }

    public function query(Contact $model): QueryBuilder
    {
        return $model->newQuery()->with([
            'enterprise',
            'sources',
            'responsible:id,name',
            'categories'
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
                ->title(__('Name'))
                ->addClass('all'),
            Column::make('sources')
                ->title(__('Networks'))
                ->className('text-center')
                ->addClass('min-phone')
                ->searchable(false)
                ->orderable(false)
                ->width(150),
            Column::make('responsible_name')
                ->title(__('Advisor'))
                ->className('text-center')
                ->addClass('min-desktop')
                ->searchable(false)
                ->orderable(false),
            Column::make('categories')
                ->title(__('Categories'))
                ->className('text-center')
                ->addClass('category-filter min-desktop')
                ->searchable(true)
                ->orderable(false),
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