<?php

namespace App\DataTables;

use App\Models\Task;
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
			->rawColumns(['action', 'status_id']);
	}

	public function query(Task $model): QueryBuilder
	{
		return $model->newQuery()
			->with([
				'responsible:id,name',
				'status',
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
			->ordering(false)
			->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
			->parameters([
				'initComplete' => "function() {
					var api = this.api();
					api.columns('.select-filter').every(function() {
						var column = this;
						$('.filter-status').on('click', function(e) {
							e.preventDefault();
							var status = $(this).data('status');
							api.column('status_id:name').search(status).draw();
						});
					});
				}",
			]);
	}

	public function getColumns(): array
	{
		return [
			Column::make('id')->hidden(),
			Column::make('title')
				->title(__('Title'))
				->addClass('all'),
			Column::make('responsible_id')
				->title(__('Responsible'))
				->addClass('min-tablet')
				->searchable(true)
				->orderable(false),
			Column::make('start_date')
				->title(__('Start date'))
				->className('text-center')
				->addClass('min-desktop')
				->searchable(false)
				->orderable(false),
			Column::make('due_date')
				->title(__('Due date'))
				->className('text-center')
				->addClass('min-desktop')
				->searchable(false)
				->orderable(false),
			Column::make('status_id')
				->title(__('Status'))
				->className('text-center')
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
		return 'Task_'.date('YmdHis');
	}
}
