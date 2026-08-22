<?php

namespace App\DataTables;

use App\Models\Contact;
use App\Support\DataTableFormatter;
use App\Support\SearchNormalizer;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
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
        $table = (new EloquentDataTable($query));

        // Add action column if user has any action permissions (always show for now, blade will handle permissions)
        $table = $table->addColumn('action', function ($contact)
        {
            return view('contact.action', compact('contact'));
        });

        return $table
            ->setRowId('id')
            ->editColumn('name', function ($row)
            {
                $fullName = e($row->name);
                if (! empty($row->surname))
                {
                    $fullName .= ' '.e($row->surname);
                }
                $companyName = $row->enterprises->first() ? e($row->enterprises->first()->name) : '';

                $nameHtml = DataTableFormatter::showLink($row, 'contact.show', $fullName, 'view', [$row->id]);

                return DataTableFormatter::nameColumn($nameHtml, $companyName ?: null);
            })
            ->filterColumn('name', function ($query, $keyword)
            {
                $keyword = trim((string) $keyword);

                if ($keyword === '')
                {
                    return;
                }

                SearchNormalizer::applyContactDataTableNameColumnConditions($query, $keyword);
            })
            ->addColumn('current_sentiment', function ($row)
            {
                if ($row->currentSentiment)
                {
                    return '<span style="font-size: 1.5em;">'.$row->currentSentiment->sentiment->emoji.'</span>';
                }

                return '<span style="font-size: 1.5em;">🤔</span>';
            })
            ->addColumn('current_intent', function ($row)
            {
                if ($row->currentSentiment?->intent)
                {
                    $intent = $row->currentSentiment->intent;

                    return '<span style="font-size: 1.5em;" title="'.e($intent->name).'">'.$intent->emoji.'</span>';
                }

                return '<span style="font-size: 1.5em;" title="'.e(__('Unclear')).'">❔</span>';
            })
            ->filterColumn('current_sentiment', function ($query, $keyword)
            {
                if ($keyword !== '' && is_numeric($keyword))
                {
                    $query->whereHas('currentSentiment', function ($q) use ($keyword)
                    {
                        $q->where('sentiment_id', $keyword);
                    });
                } elseif ($keyword !== '')
                {
                    $query->whereRaw('0 = 1');
                }
            })
            ->filterColumn('current_intent', function ($query, $keyword)
            {
                if ($keyword !== '' && is_numeric($keyword))
                {
                    $query->whereHas('currentSentiment', function ($q) use ($keyword)
                    {
                        $q->where('intent_id', $keyword);
                    });
                } elseif ($keyword !== '')
                {
                    $query->whereRaw('0 = 1');
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
                $badges = $row->categories->map(function ($category)
                {
                    return '<span class="badge bg-label-primary me-1">'.e($category->name).'</span>';
                })->join(' ');

                return $badges !== '' ? $badges : '&nbsp;';
            })
            ->filterColumn('categories', function ($query, $keyword)
            {
                if ($keyword !== '' && is_numeric($keyword))
                {
                    $query->whereHas('categories', function ($q) use ($keyword)
                    {
                        $q->where('id', $keyword);
                    });
                } elseif ($keyword !== '')
                {
                    $query->whereRaw('0 = 1');
                }
            })
            ->editColumn('status_id', function ($row)
            {
                return $row->status_label;
            })
            ->rawColumns(['name', 'action', 'current_sentiment', 'current_intent', 'sources', 'status_id', 'categories']);
    }

    public function query(Contact $model): QueryBuilder
    {
        $query = $model->newQuery()
            ->where('team_id', Auth::user()->currentTeam->id)
            ->with([
                'list60:id,contact_id',
                'enterprises:id,name',
                'currentSentiment.sentiment',
                'currentSentiment.intent',
                'status',
                'sources',
                'responsible:id,name',
                'categories',
                'user.roles',
                'user.teams',
                'user.currentTeam.settings',
            ]);

        return $query;
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
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'initComplete' => "function() {
					var api = this.api();

					function syncContactListToolbarSelect2() {
						var emotionalVal = $('#EmotionalState').val();
						if (emotionalVal) {
							$('#EmotionalState').val(emotionalVal).trigger('change.select2');
						}
						var intentVal = $('#IntentFilter').val();
						if (intentVal) {
							$('#IntentFilter').val(intentVal).trigger('change.select2');
						}
						var categoryVal = $('#CategoryFilter').val();
						if (categoryVal) {
							$('#CategoryFilter').val(categoryVal).trigger('change.select2');
						}
					}

					api.columns('.select-filter').every(function() {
						var column = this;
						$('#EmotionalState').off('change.contactFilter').on('change.contactFilter', function() {
							var val = $(this).val();
							column.search(val ? val : '', true, false).draw();
						});
					});

					$('#IntentFilter').off('change.contactFilter').on('change.contactFilter', function() {
						var val = $(this).val();
						api.column('.intent-filter').search(val ? val : '', true, false).draw();
					});

					$('#CategoryFilter').off('change.contactFilter').on('change.contactFilter', function() {
						var val = $(this).val();
						api.column('.category-filter').search(val ? val : '', true, false).draw();
					});

					$('.filter-status').off('click.contactFilter').on('click.contactFilter', function(e) {
						e.preventDefault();
						api.column('status_id:name').search($(this).data('status')).draw();
					});

					var savedEmotional = sessionStorage.getItem('contact_list_emotional_state');
					var savedIntent = sessionStorage.getItem('contact_list_intent');
					var savedCategory = sessionStorage.getItem('contact_list_category');
					if (savedEmotional) {
						$('#EmotionalState').val(savedEmotional).trigger('change.select2');
						api.columns('.select-filter').search(savedEmotional, true, false);
					}
					if (savedIntent) {
						$('#IntentFilter').val(savedIntent).trigger('change.select2');
						api.column('.intent-filter').search(savedIntent, true, false);
					}
					if (savedCategory) {
						$('#CategoryFilter').val(savedCategory).trigger('change.select2');
						api.column('.category-filter').search(savedCategory, true, false);
					}
					if (savedEmotional || savedIntent || savedCategory) {
						api.draw();
					}

					$('#EmotionalState').off('change.contactFilterPersist').on('change.contactFilterPersist', function() {
						var val = $(this).val();
						if (val) {
							sessionStorage.setItem('contact_list_emotional_state', val);
						} else {
							sessionStorage.removeItem('contact_list_emotional_state');
						}
					});

					$('#IntentFilter').off('change.contactFilterPersist').on('change.contactFilterPersist', function() {
						var val = $(this).val();
						if (val) {
							sessionStorage.setItem('contact_list_intent', val);
						} else {
							sessionStorage.removeItem('contact_list_intent');
						}
					});

					$('#CategoryFilter').off('change.contactFilterPersist').on('change.contactFilterPersist', function() {
						var val = $(this).val();
						if (val) {
							sessionStorage.setItem('contact_list_category', val);
						} else {
							sessionStorage.removeItem('contact_list_category');
						}
					});

					syncContactListToolbarSelect2();
				}",
                'drawCallback' => "function() {
					var emotionalVal = $('#EmotionalState').val();
					if (emotionalVal) {
						$('#EmotionalState').val(emotionalVal).trigger('change.select2');
					}
					var intentVal = $('#IntentFilter').val();
					if (intentVal) {
						$('#IntentFilter').val(intentVal).trigger('change.select2');
					}
					var categoryVal = $('#CategoryFilter').val();
					if (categoryVal) {
						$('#CategoryFilter').val(categoryVal).trigger('change.select2');
					}
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
                ->width(80),
            Column::make('current_intent')
                ->title(__('Intent'))
                ->className('text-center')
                ->addClass('intent-filter min-tablet')
                ->searchable(true)
                ->orderable(false)
                ->width(80),
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
