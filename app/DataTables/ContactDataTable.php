<?php

namespace App\DataTables;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ContactDataTable extends DataTable
{
    private function debugLog(string $runId, string $hypothesisId, string $location, string $message, array $data = []): void
    {
        try
        {
            $payload = json_encode([
                'sessionId' => '934817',
                'runId' => $runId,
                'hypothesisId' => $hypothesisId,
                'location' => $location,
                'message' => $message,
                'data' => $data,
                'timestamp' => round(microtime(true) * 1000),
            ], JSON_UNESCAPED_SLASHES);

            if ($payload !== false)
            {
                file_put_contents(base_path('.cursor/debug-934817.log'), $payload.PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        } catch (\Throwable $e)
        {
            // Intentionally swallow debug logging errors.
        }
    }

    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        // #region agent log
        $requestSearch = (string) request()->input('search.value', '');
        $this->debugLog(
            'pre-fix',
            'H1',
            'app/DataTables/ContactDataTable.php:dataTable',
            'DataTable entry',
            [
                'team_id' => (int) (Auth::user()->currentTeam->id ?? 0),
                'search_present' => $requestSearch !== '',
                'search_len' => strlen($requestSearch),
            ],
        );
        // #endregion

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

                return '<div class="d-flex flex-column">
							<span class="fw-medium text-body text-truncate">'.$fullName.'</span>
							<small class="text-muted">'.($companyName ?: '&nbsp;').'</small>
						</div>';
            })
            ->filterColumn('name', function ($query, $keyword)
            {
                // #region agent log
                $this->debugLog(
                    'pre-fix',
                    'H2',
                    'app/DataTables/ContactDataTable.php:filterColumn(name)',
                    'Name filter invoked',
                    [
                        'keyword_len' => strlen((string) $keyword),
                        'keyword_non_empty' => trim((string) $keyword) !== '',
                    ],
                );
                // #endregion

                // Search in both name and surname fields, and enterprise name
                $query->where(function ($q) use ($keyword)
                {
                    $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('surname', 'like', "%{$keyword}%")
                        ->orWhereHas('enterprises', function ($enterpriseQuery) use ($keyword)
                        {
                            $enterpriseQuery->where('name', 'like', "%{$keyword}%");
                        });
                });
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
                // #region agent log
                $this->debugLog(
                    'post-fix',
                    'H4',
                    'app/DataTables/ContactDataTable.php:filterColumn(current_sentiment)',
                    'Current sentiment filter invoked',
                    [
                        'keyword' => (string) $keyword,
                        'is_numeric' => is_numeric($keyword),
                    ],
                );
                // #endregion

                if ($keyword !== '' && is_numeric($keyword))
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
                $badges = $row->categories->map(function ($category)
                {
                    return '<span class="badge bg-label-primary me-1">'.e($category->name).'</span>';
                })->join(' ');

                return $badges !== '' ? $badges : '&nbsp;';
            })
            ->filterColumn('categories', function ($query, $keyword)
            {
                // #region agent log
                $this->debugLog(
                    'post-fix',
                    'H5',
                    'app/DataTables/ContactDataTable.php:filterColumn(categories)',
                    'Categories filter invoked',
                    [
                        'keyword' => (string) $keyword,
                        'is_numeric' => is_numeric($keyword),
                    ],
                );
                // #endregion

                if ($keyword !== '' && is_numeric($keyword))
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
        // #region agent log
        $this->debugLog(
            'pre-fix',
            'H3',
            'app/DataTables/ContactDataTable.php:query',
            'Base query created',
            [
                'team_id' => (int) (Auth::user()->currentTeam->id ?? 0),
                'user_role_collaborator' => (bool) (Auth::user()?->hasRole('collaborator') ?? false),
            ],
        );
        // #endregion

        $query = $model->newQuery()
            ->where('team_id', Auth::user()->currentTeam->id)
            ->with([
                'list60:id,contact_id',
                'enterprises:id,name',
                'currentSentiment.sentiment',
                'status',
                'sources',
                'responsible:id,name',
                'categories',
                'user.roles',
                'user.teams',
                'user.currentTeam.settings',
            ]);

        // Collaborators only see their assigned contacts
        $user = Auth::user();
        if ($user && $user->hasRole('collaborator'))
        {
            $query->where('responsible_id', $user->id);
        }

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
