<?php

namespace App\DataTables;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class EmployeeDataTable extends DataTable
{
	/**
	 * Build the DataTable class.
	 *
	 * @param  QueryBuilder  $query  Results from query() method.
	 */
	public function dataTable(QueryBuilder $query): EloquentDataTable
	{
		return (new EloquentDataTable($query))
			->addColumn('action', function ($contact)
			{
				return view('employee.action', compact('contact'));
			})
			->setRowId('id')
			->editColumn('name', function ($row)
			{
				$fullName = e($row->name);
				if (! empty($row->surname))
				{
					$fullName .= ' '.e($row->surname);
				}

				return '<div class="d-flex flex-column">
							<span class="fw-medium text-body text-truncate">'.$fullName.'</span>
						</div>';
			})
			->addColumn('city', function ($row)
			{
				return $row->data->city ?? '-';
			})
			->addColumn('province', function ($row)
			{
				return $row->data->province ?? '-';
			})
			->addColumn('command', function ($row)
			{
				return $row->data->command ?? '-';
			})
			->addColumn('active', function ($row)
			{
				$isActive = $row->data->active ?? true;
				if ($isActive)
				{
					return '<span class="badge bg-label-success"><i class="ti ti-check ti-sm"></i></span>';
				} else
				{
					return '<span class="badge bg-label-danger"><i class="ti ti-x ti-sm"></i></span>';
				}
			})
			->addColumn('responsible_name', function ($contact)
			{
				return $contact->responsible->name ?? __('Unassigned');
			})
			->filterColumn('responsible_name', function ($query, $keyword)
			{
				$query->whereHas('responsible', function ($q) use ($keyword)
				{
					$q->where('name', 'like', "%{$keyword}%");
				});
			})
			->editColumn('status_id', function ($row)
			{
				return $row->status_label;
			})
			->rawColumns(['name', 'action', 'active', 'status_id']);
	}

	public function query(Contact $model): QueryBuilder
	{
		return $model->newQuery()
			->whereHas('user', function ($query)
			{
				$query->whereHas('roles', function ($q)
				{
					$q->where('name', 'employee');
				});
			})
			->with([
				'status',
				'responsible:id,name',
			]);
	}

	public function html(): HtmlBuilder
	{
		return $this->builder()
			->setTableId('employee-table')
			->columns($this->getColumns())
			->minifiedAjax()
			->dom('frtip')
			->orderBy(1, 'asc')
			->responsive(true)
			->processing(false)
			->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
			->parameters([
				'select' => false,
				'autoWidth' => false,
				'drawCallback' => 'function() {
					$("#employee-table tbody tr").css({
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
			Column::make('id')->hidden(),
			Column::make('name')
				->title(__('Name'))
				->addClass('all'),
			Column::make('city')
				->title(__('City'))
				->addClass('min-tablet'),
			Column::make('province')
				->title(__('Province'))
				->addClass('min-tablet'),
			Column::make('email')
				->title(__('Email'))
				->addClass('min-desktop'),
			Column::make('command')
				->title(__('Command'))
				->addClass('min-tablet'),
			Column::make('active')
				->title(__('Active'))
				->className('text-center')
				->addClass('min-tablet'),
			Column::make('status_id')
				->title(__('Status'))
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
		return 'Employee_'.date('YmdHis');
	}
}
