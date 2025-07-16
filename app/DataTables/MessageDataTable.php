<?php

namespace App\DataTables;

use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class MessageDataTable extends DataTable
{
	/**
	 * Build the DataTable class.
	 *
	 * @param  QueryBuilder  $query  Results from query() method.
	 */
	public function dataTable(QueryBuilder $query): EloquentDataTable
	{
		return (new EloquentDataTable($query))
			->addColumn('action', 'message.action')
			->setRowId('id')
			->rawColumns(['name', 'action', 'status_id'])
			->editColumn('type_id', function ($data) {
				return $data->type->name;
			})
			->editColumn('category_id', function ($data) {
				return optional($data->category)->name;
			})
			->editColumn('updated_at', function ($data) {
				return Carbon::parse($data->updated_at)->format('d-m-Y H:i:s');
			})
			->editColumn('status_id', function ($data) {
				$statusValue = is_object($data->status_id) ? $data->status_id->value : $data->status_id;

				if ($statusValue == 2) {
					return '<span class="badge rounded-pill bg-label-success">Active</span>';
				} else {
					return '<span class="badge rounded-pill bg-label-warning">Inactive</span>';
				}
			});
	}

	public function query(Message $model): QueryBuilder
	{
		return $model->newQuery();
	}

	public function html(): HtmlBuilder
	{
		return $this->builder()
			->setTableId('message-table')
			->columns($this->getColumns())
			->minifiedAjax()
			->dom('frtip')
			->orderBy(1)
			->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json']);
	}

	public function getColumns(): array
	{
		return [
			Column::make('id')->hidden(),
			Column::make('name')
				->title(__('Name'))
				->addClass('all'),
			Column::make('type_id')
				->title(__('Type'))
				->addClass('min-tablet'),
			Column::make('category_id')
				->title(__('Category'))
				->addClass('min-desktop'),
			Column::make('updated_at')
				->title(__('Updated'))
				->className('text-center')
				->addClass('min-tablet'),
			Column::make('status_id')
				->title(__('Status'))
				->className('text-center')
				->addClass('min-tablet'),
			Column::computed('action')
				->title(__('Actions'))
				->width(20)
				->className('text-center')
				->exportable(false)
				->printable(false)
				->width(30)
				->addClass('min-desktop'),
		];
	}

	protected function filename(): string
	{
		return 'Message_'.date('YmdHis');
	}
}
