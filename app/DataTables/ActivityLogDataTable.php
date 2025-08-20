<?php

namespace App\DataTables;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ActivityLogDataTable extends DataTable
{
	/**
	 * Build DataTable class.
	 *
	 * @param  QueryBuilder  $query  Results from query() method.
	 */
	public function dataTable(QueryBuilder $query): EloquentDataTable
	{
		return (new EloquentDataTable($query))
			->addIndexColumn()
			->addColumn('user', function (Activity $activity)
			{
				if ($activity->causer)
				{
					return $activity->causer->name;
				}

				return '<span class="text-muted">Sistema</span>';
			})
			->addColumn('subject', function (Activity $activity)
			{
				if ($activity->subject)
				{
					$modelName = class_basename($activity->subject_type);

					return $modelName.' #'.$activity->subject_id;
				}

				return '<span class="text-muted">-</span>';
			})
			->addColumn('description', function (Activity $activity)
			{
				return '<span class="badge bg-label-primary">'.$activity->description.'</span>';
			})
			->addColumn('properties', function (Activity $activity)
			{
				if ($activity->properties && $activity->properties->count() > 0)
				{
					$html = '<button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse"
							   data-bs-target="#collapse-'.$activity->id.'" aria-expanded="false">
							   <i class="ti ti-eye ti-sm"></i>
						   </button>';
					$html .= '<div class="collapse mt-2" id="collapse-'.$activity->id.'">';
					$html .= '<div class="card card-body p-2" style="font-size: 11px;">';

					foreach ($activity->properties as $key => $value)
					{
						if (is_array($value) || is_object($value))
						{
						    $value = json_encode($value, JSON_PRETTY_PRINT);
						}
						$html .= '<strong>'.ucfirst($key).':</strong> '.$value.'<br>';
					}

					$html .= '</div></div>';

					return $html;
				}

				return '<span class="text-muted">-</span>';
			})
			->addColumn('created_at', function (Activity $activity)
			{
				return $activity->created_at->format('d/m/Y H:i:s');
			})
			->addColumn('action', 'activity-log.action')
			->rawColumns(['user', 'subject', 'description', 'properties', 'action']);
	}

	/**
	 * Get query source of dataTable.
	 */
	public function query(Activity $model): QueryBuilder
	{
		$query = $model->newQuery()
			->with(['causer', 'subject'])
			->latest();

		// Filter by team if user has currentTeam
		if (auth()->check() && auth()->user()->currentTeam)
		{
			$teamUserIds = auth()->user()->currentTeam->users->pluck('id');
			$query->whereIn('causer_id', $teamUserIds);
		}

		return $query;
	}

	/**
	 * Optional method if you want to use html builder.
	 */
	public function html(): HtmlBuilder
	{
		return $this->builder()
			->setTableId('activitylog-table')
			->columns($this->getColumns())
			->minifiedAjax()
			->dom('Bfrtip')
			->orderBy(0, 'desc')
			->selectStyleSingle()
			->buttons([
				Button::make('export'),
				Button::make('print'),
				Button::make('reload'),
			])
			->parameters([
				'language' => [
					'url' => asset('assets/json/datatables/'.app()->getLocale().'.json'),
				],
				'select' => false,
				'autoWidth' => false,
				'drawCallback' => 'function() {
					$("#activitylog-table tbody tr").css({
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
			Column::make('DT_RowIndex')
				->title('#')
				->searchable(false)
				->orderable(false)
				->width(50),

			Column::make('user')
				->title(__('User'))
				->searchable(true)
				->orderable(true),

			Column::make('description')
				->title(__('Activity'))
				->searchable(true)
				->orderable(true),

			Column::make('subject')
				->title(__('Subject'))
				->searchable(true)
				->orderable(true),

			Column::make('properties')
				->title(__('Details'))
				->searchable(false)
				->orderable(false),

			Column::make('created_at')
				->title(__('Date'))
				->searchable(true)
				->orderable(true),
		];
	}

	/**
	 * Get filename for export.
	 */
	protected function filename(): string
	{
		return 'ActivityLog_'.date('YmdHis');
	}
}
