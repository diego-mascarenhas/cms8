<?php

namespace App\DataTables;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ContactDataTable extends DataTable
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
				return view('contact.action', compact('contact'));
			})
			->setRowId('id')
			->editColumn('name', function ($row)
			{
				$fullName = e($row->name);
				if (! empty($row->surname))
				{
					$fullName .= ' '.e($row->surname);
				}
				$companyName = $row->enterprises->first() ? e($row->enterprises->first()->name) : '';

				return '<div class="d-flex flex-column">
							<span class="fw-medium text-body text-truncate">'.$fullName.'</span>
							<small class="text-muted">'.($companyName ?: '&nbsp;').'</small>
						</div>';
			})
			->addColumn('current_sentiment', function ($row)
			{
				if ($row->currentSentiment)
				{
					return '<span style="font-size: 1.5em;">'.$row->currentSentiment->sentiment->emoji.'</span>';
				}

				return '<span style="font-size: 1.5em;">🤔</span>';
			})
			->filterColumn('current_sentiment', function ($query, $keyword)
			{
				if ($keyword !== '')
				{
					$query->whereHas('currentSentiment', function ($q) use ($keyword)
					{
						$q->where('sentiment_id', $keyword);
					});
				}
			})
			->addColumn('sources', function ($row)
			{
				return $row->sources_icons_html;
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
			->addColumn('categories', function ($row)
			{
				return $row->categories->map(function ($category)
				{
					return '<span class="badge bg-label-primary me-1">'.e($category->name).'</span>';
				})->join(' ');
			})
			->filterColumn('categories', function ($query, $keyword)
			{
				if ($keyword !== '')
				{
					$query->whereHas('categories', function ($q) use ($keyword)
					{
						$q->where('id', $keyword);
					});
				}
			})
			->editColumn('status_id', function ($row)
			{
				return $row->status_label;
			})
			->rawColumns(['name', 'action', 'current_sentiment', 'sources', 'status_id', 'categories']);
	}

	public function query(Contact $model): QueryBuilder
	{
		return $model->newQuery()->with([
			'list60:id,contact_id',
			'enterprises:id,name',
			'currentSentiment.sentiment',
			'status',
			'sources',
			'responsible:id,name',
			'categories',
		]);
	}

	public function html(): HtmlBuilder
	{
		return $this->builder()
			->setTableId('contact-table')
			->columns($this->getColumns())
			->minifiedAjax()
			->dom('frtip')
			->orderBy(1, 'asc')
			->responsive(true)
			->processing(false)
			->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
			->parameters([
				'initComplete' => "function() {
					var api = this.api();
					api.columns('.select-filter').every(function() {
						var column = this;
						$('#EmotionalState').on('change', function() {
							var val = $.fn.dataTable.util.escapeRegex($(this).val());
							column.search(val ? val : '', true, false).draw();
						});

						$('.filter-status').on('click', function(e) {
							e.preventDefault();
							var status = $(this).data('status');
							api.column('status_id:name').search(status).draw();
						});
					});
				}",
				'drawCallback' => "function() {
					$('#EmotionalState').off('change').on('change', function() {
						$('#contact-table').DataTable().columns('.select-filter').search($(this).val()).draw();
					});
					$('#CategoryFilter').off('change').on('change', function() {
						let selectedValue = $(this).val();
						$('#contact-table').DataTable().column(5).search(selectedValue ? selectedValue : '', true, false).draw();
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
			Column::make('current_sentiment')
				->title(__('Sentiment'))
				->className('text-center')
				->addClass('select-filter min-tablet')
				->searchable(true)
				->orderable(false)
				->width(150),
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
		return 'Contact_'.date('YmdHis');
	}
}
