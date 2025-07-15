<?php

namespace App\DataTables;

use App\Models\CustomerFare;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class CustomerFareDataTable extends DataTable
{
	/**
	 * Build the DataTable class.
	 */
	public function dataTable(QueryBuilder $query): EloquentDataTable
	{
		return (new EloquentDataTable($query))
			->addColumn('action', function ($customerFare) {
				return view('customer-fare.action', compact('customerFare'));
			})
			->addColumn('fare_info', function ($customerFare) {
				$fareBlock = $customerFare->fare->block ? $customerFare->fare->block->name : 'N/A';

				return '<div>
					<strong>'.$customerFare->fare->name.'</strong><br>
					<small class="text-muted">'.$fareBlock.'</small>
				</div>';
			})
			->addColumn('unit_type', function ($customerFare) {
				return $customerFare->fare->unit ? $customerFare->fare->unit->type : 'N/A';
			})
			->addColumn('languages', function ($customerFare) {
				$origin = $customerFare->languageOrigin ? $customerFare->languageOrigin->name : 'N/A';
				$destination = $customerFare->languageDestination ? $customerFare->languageDestination->name : 'N/A';

				return $origin.' → '.$destination;
			})
			->addColumn('amount_formatted', function ($customerFare) {
				$negotiableBadge = $customerFare->negotiable ?
					'<span class="badge bg-label-warning ms-1">Negotiable</span>' : '';

				return $customerFare->formatted_amount.$negotiableBadge;
			})
			->setRowId('id')
			->rawColumns(['action', 'fare_info', 'amount_formatted']);
	}

	/**
	 * Get the query source of dataTable.
	 */
	public function query(CustomerFare $model): QueryBuilder
	{
		return $model->newQuery()
			->with(['fare.unit', 'fare.block', 'languageOrigin', 'languageDestination', 'currency'])
			->when(request('customer_id'), function ($query, $customerId) {
				return $query->where('customer_id', $customerId);
			});
	}

	/**
	 * Optional method to set up the DataTable's HTML table tag.
	 */
	public function html(): HtmlBuilder
	{
		return $this->builder()
			->setTableId('customer-fare-table')
			->columns($this->getColumns())
			->minifiedAjax()
			->dom('Bfrtip')
			->orderBy(0)
			->selectStyleSingle()
			->buttons([
				['extend' => 'export', 'className' => 'btn btn-outline-secondary'],
				['extend' => 'print', 'className' => 'btn btn-outline-secondary'],
				['extend' => 'reset', 'className' => 'btn btn-outline-secondary'],
				['extend' => 'reload', 'className' => 'btn btn-outline-secondary'],
			]);
	}

	/**
	 * Get the dataTable columns definition.
	 */
	public function getColumns(): array
	{
		return [
			Column::make('id')->hidden(),
			Column::computed('fare_info')
				->title('Tarifa')
				->addClass('all'),
			Column::computed('unit_type')
				->title('Unidad')
				->addClass('min-tablet')
				->className('text-center'),
			Column::computed('languages')
				->title('Idiomas')
				->addClass('min-tablet'),
			Column::computed('amount_formatted')
				->title('Precio')
				->addClass('min-tablet')
				->className('text-center'),
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

	/**
	 * Get the filename for export.
	 */
	protected function filename(): string
	{
		return 'CustomerFare_'.date('YmdHis');
	}
}
