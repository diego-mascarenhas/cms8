<?php

namespace App\DataTables;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class NotificationDataTable extends DataTable
{
	/**
	 * Build DataTable class.
	 */
	public function dataTable(QueryBuilder $query): EloquentDataTable
	{
		return (new EloquentDataTable($query))
			->addColumn('action', function ($notification) {
				return view('notification.action', compact('notification'));
			})
			->addColumn('contact_name', function ($notification) {
				return $notification->contact ? $notification->contact->name.' '.$notification->contact->surname : 'N/A';
			})
			->addColumn('type_name', function ($notification) {
				return $notification->type ? $notification->type->name : 'N/A';
			})
			->addColumn('status', function ($notification) {
				return $notification->status_badge;
			})
			->addColumn('read_status', function ($notification) {
				return $notification->read_status_badge;
			})
			->addColumn('sent_date', function ($notification) {
				return $notification->formatted_sent_date;
			})
			->editColumn('created_at', function ($notification) {
				return $notification->created_at->format('d/m/Y H:i');
			})
			->editColumn('subject', function ($notification) {
				return '<span title="'.e($notification->subject).'">'.
					   e(\Str::limit($notification->subject, 50)).'</span>';
			})
			->rawColumns(['action', 'status', 'read_status', 'subject'])
			->setRowId('id');
	}

	/**
	 * Get query source of dataTable.
	 */
	public function query(Notification $model): QueryBuilder
	{
		$query = $model->newQuery()
			->with(['contact', 'type', 'user'])
			->orderBy('created_at', 'desc');

		// Apply filters
		if (request()->has('status') && request()->status != '') {
			$status = request()->status;
			if ($status === 'sent') {
				$query->where('is_sent', true);
			} elseif ($status === 'unsent') {
				$query->where('is_sent', false);
			}
		}

		if (request()->has('type') && request()->type != '') {
			$query->where('type_id', request()->type);
		}

		if (request()->has('date_from') && request()->date_from != '') {
			$query->whereDate('created_at', '>=', request()->date_from);
		}

		if (request()->has('date_to') && request()->date_to != '') {
			$query->whereDate('created_at', '<=', request()->date_to);
		}

		return $query;
	}

	/**
	 * Optional method if you want to use html builder.
	 */
	public function html(): HtmlBuilder
	{
		return $this->builder()
			->setTableId('notifications-table')
			->columns($this->getColumns())
			->minifiedAjax()
			->dom('frtip')
			->orderBy(7, 'desc') // Order by created_at column (index 7)
			->selectStyleSingle()
			->responsive(true)
			->processing(false)
			->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
			->parameters([
				'columnDefs' => [
					[
						'targets' => [0], // ID column
						'visible' => false,
						'searchable' => false,
						'orderable' => false,
					],
				],
				'pageLength' => 25,
				'select' => false, // Disable row selection
				'autoWidth' => false,
				'drawCallback' => 'function() {
					// Disable text selection on table rows
					$("#notifications-table tbody tr").css({
						"user-select": "none",
						"-webkit-user-select": "none",
						"-moz-user-select": "none",
						"-ms-user-select": "none"
					});
				}',
			]);
	}

	/**
	 * Get the dataTable columns definition.
	 */
	public function getColumns(): array
	{
		return [
			Column::make('id')->title('ID')->width(60)->visible(false),
			Column::make('subject')->title('Asunto'),
			Column::make('contact_name')->title('Contacto')->searchable(false)->orderable(false),
			Column::make('type_name')->title('Tipo')->searchable(false)->orderable(false),
			Column::make('status')->title('Estado')->searchable(false)->orderable(false),
			Column::make('read_status')->title('Leído')->searchable(false)->orderable(false),
			Column::make('sent_date')->title('Enviado')->searchable(false)->orderable(false),
			Column::make('created_at')->title('Creado'),
			Column::computed('action')
				->title('Acciones')
				->exportable(false)
				->printable(false)
				->width(120)
				->addClass('text-center'),
		];
	}

	/**
	 * Get filename for export.
	 */
	protected function filename(): string
	{
		return 'Notifications_'.date('YmdHis');
	}
}
