<?php

namespace App\DataTables;

use App\Models\Task;
use App\Support\DataTableFormatter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class TaskDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'task.action')
            ->setRowId('id')
            ->editColumn('title', function ($row)
            {
                return DataTableFormatter::showLink($row, 'task.edit', $row->title, 'update', [$row->id]);
            })
            ->addColumn('project_name', function ($row)
            {
                return $row->project ? ($row->project->real_name ?: $row->project->name) : __('No project');
            })
            ->editColumn('responsible_id', function ($data)
            {
                return $data->responsible->name ?? __('Unassigned');
            })
            ->filterColumn('responsible_id', function ($query, $keyword)
            {
                $query->whereHas('responsible', function ($q) use ($keyword)
                {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            })
            ->editColumn('start_date', function ($data)
            {
                return Carbon::parse($data->start_date)->format('d-m-Y');
            })
            ->editColumn('due_date', function ($data)
            {
                return Carbon::parse($data->due_date)->format('d-m-Y');
            })
            ->editColumn('status_id', function ($row)
            {
                return $row->status_label;
            })
            ->rawColumns(['action', 'title', 'status_id']);
    }

    public function query(Task $model): QueryBuilder
    {
        return $model->newQuery()
            ->with([
                'responsible',
                'status',
                'project:id,real_name,name,board_id',
            ])
            ->defaultOrder();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('task-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
            ->parameters([
                'autoWidth' => false,
                'columnDefs' => [
                    [
                        'targets' => 0,
                        'visible' => false,
                        'searchable' => false,
                        'orderable' => false,
                    ],
                ],
                'initComplete' => "function() {
					var api = this.api();
					api.columns.adjust();
					if (api.responsive) {
						api.responsive.recalc();
					}
					api.columns('.select-filter').every(function() {
						var column = this;
						$('.filter-status').on('click', function(e) {
							e.preventDefault();
							var status = $(this).data('status');
							api.column('status_id:name').search(status).draw();
						});
					});
				}",
                'drawCallback' => 'function() {
					var api = this.api();
					api.columns.adjust();
					if (api.responsive) {
						api.responsive.recalc();
					}
					$("#task-table tbody tr").css({
						"user-select": "none",
						"-webkit-user-select": "none",
						"-moz-user-select": "none",
						"-ms-user-select": "none"
					});
				}',
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden()->searchable(false),
            Column::make('title')
                ->title(__('Title'))
                ->addClass('all'),
            Column::make('project_name')
                ->title(__('Project'))
                ->addClass('min-desktop')
                ->orderable(false)
                ->searchable(false),
            Column::make('responsible_id')
                ->title(__('Responsible'))
                ->addClass('min-tablet')
                ->searchable(true)
                ->orderable(false),
            Column::make('start_date')
                ->title(__('app.task_table_start'))
                ->className('text-center')
                ->addClass('min-desktop')
                ->width(105)
                ->searchable(false)
                ->orderable(false),
            Column::make('due_date')
                ->title(__('app.task_table_due'))
                ->className('text-center')
                ->addClass('min-desktop')
                ->width(115)
                ->searchable(false)
                ->orderable(false),
            Column::make('status_id')
                ->title(__('Status'))
                ->className('text-center')
                ->addClass('min-tablet')
                ->width(120)
                ->orderable(false),
            Column::computed('action')
                ->title(__('Actions'))
                ->width(80)
                ->className('text-center')
                ->addClass('min-desktop')
                ->exportable(false)
                ->printable(false),
        ];
    }

    protected function filename(): string
    {
        return 'Task_'.date('YmdHis');
    }
}
