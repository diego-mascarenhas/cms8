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
			->editColumn('date', function ($data)
            {
                $formated_date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $data->date)->format('d-m-Y H:i:s');
                return $formated_date;
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
			Column::make('date'),
			Column::make('phone')->orderable(false),
			Column::make('answer')->orderable(false),
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