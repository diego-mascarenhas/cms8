<?php

namespace App\DataTables;

use App\Models\History;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

use Carbon\Carbon;

class WhastappDataTable extends DataTable
{
	/**
	 * Build the DataTable class.
	 *
	 * @param QueryBuilder $query Results from query() method.
	 */
	public function dataTable(QueryBuilder $query): EloquentDataTable
	{
		return (new EloquentDataTable($query))
			->addColumn('action', 'whastapp.action')
			->setRowId('id')
			->rawColumns(['verified'])
			->editColumn('date', function ($data)
            {
				return Carbon::parse($data->date)->format('d-m-Y H:i:s');
            })
			->editColumn('phone', function ($data)
            {
                return $data->user ? $data->user->name : $data->phone;
            })
			->addColumn('verified', function ($data) {
                if ($data->user) {
                    return $data->user->email_verified_at
                        ? '<i class="ti fs-4 ti-shield-check text-success"></i>'
                        : '<i class="ti fs-4 ti-shield-x text-warning"></i>';
                }
                return '<i class="ti fs-4 ti-shield-x text-danger"></i>';
            });
	}

	/**
	 * Get the query source of dataTable.
	 */
	public function query(History $model): QueryBuilder
	{
		return $model->newQuery();
	}

	/**
	 * Optional method if you want to use the html builder.
	 */
	public function html(): HtmlBuilder
	{
		return $this->builder()
			->setTableId('whastapp-table')
			->columns($this->getColumns())
			->minifiedAjax()
			//->dom('Bfrtip')
			->orderBy(1, 'desc')
			->selectStyleSingle();
	}

	/**
	 * Get the dataTable columns definition.
	 */
	public function getColumns(): array
	{
		return [
			Column::make('id')->hidden(),
			Column::make('date')->width(180),
			Column::make('phone')->width(200)->orderable(false),
			Column::make('answer')->orderable(false),
			Column::make('verified')->width(20)->className('text-center'),
		];
	}

	/**
	 * Get the filename for export.
	 */
	protected function filename(): string
	{
		return 'Whastapp_' . date('YmdHis');
	}
}