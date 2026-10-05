<?php

namespace App\DataTables;

use App\Models\Enterprise;
use App\Support\DataTableFormatter;
use App\Support\SearchNormalizer;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ClientDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'client.action')
            ->setRowId('id')
            ->editColumn('name', function ($row)
            {
                $nameHtml = DataTableFormatter::showLink($row, 'client.show', $row->name, 'view', [$row->id]);
                $subtitle = $row->trashed()
                    ? $this->archivedSubtitle($row)
                    : ($row->responsible->name ?? 'Sin asignar');

                return DataTableFormatter::nameColumn($nameHtml, $subtitle);
            })
            ->addColumn('sources', function ($row)
            {
                return $row->sources_icons_html;
            })
            ->addColumn('responsible_name', function ($contact)
            {
                if ($contact->responsible)
                {
                    return e($contact->responsible->name);
                }

                return '<span class="text-muted">-</span>';
            })
            ->filterColumn('name', function ($query, $keyword)
            {
                $keyword = trim((string) $keyword);

                if ($keyword === '')
                {
                    return;
                }

                SearchNormalizer::applyClientDataTableNameColumnConditions($query, $keyword);
            })
            ->filterColumn('responsible_name', function ($query, $keyword)
            {
                $keyword = trim((string) $keyword);

                if ($keyword === '')
                {
                    return;
                }

                $query->whereHas('responsible', function ($q) use ($keyword)
                {
                    SearchNormalizer::applyCollaboratorNameCondition($q, $keyword);
                });
            })
            ->editColumn('status_id', function ($row)
            {
                return $row->status_label;
            })
            ->rawColumns(['name', 'action', 'sources', 'responsible_name', 'status_id', 'website', 'phone']);
    }

    public function query(Enterprise $model): QueryBuilder
    {
        $query = request()->boolean('archived')
            ? $model->newQuery()->onlyTrashed()
            : $model->newQuery()->activeClients();

        return $query
            ->select([
                'enterprises.id',
                'enterprises.name',
                'enterprises.responsible_id',
                'enterprises.status_id',
                'enterprises.team_id',
                'enterprises.data',
                'enterprises.deleted_at',
            ])
            ->with([
                'responsible:id,name',
                'status:id,name,label_class',
            ]);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('client-table')
            ->columns($this->getColumns())
            ->minifiedAjax('', "data.archived = window.clientListArchived || '0';")
            ->dom('frtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'initComplete' => "function() {
					var filter = document.getElementById('client-table_filter');
					var archiveFilter = document.getElementById('client-archive-filter');
					if (filter && archiveFilter) {
						filter.prepend(archiveFilter);
						archiveFilter.classList.remove('d-none');
					}
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
						$('#client-table').DataTable().columns('.select-filter').search($(this).val()).draw();
					});
				}",
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden()->searchable(false),
            Column::make('name')
                ->title(__('Client'))
                ->addClass('all'),
            Column::make('sources')
                ->title(__('Networks'))
                ->className('text-center')
                ->addClass('min-desktop')
                ->searchable(false)
                ->orderable(false)
                ->width(150),
            Column::make('responsible_name')
                ->title(__('Responsable'))
                ->className('text-center')
                ->addClass('min-tablet')
                ->searchable(false)
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
        return 'Client_'.date('YmdHis');
    }

    /**
     * @var array<int, ?string>
     */
    private array $mergedIntoNames = [];

    private function archivedSubtitle(Enterprise $row): string
    {
        $mergedId = (int) data_get($row->data, 'merged_into_enterprise_id');
        if ($mergedId < 1)
        {
            return 'Archivada';
        }

        if (! array_key_exists($mergedId, $this->mergedIntoNames))
        {
            $this->mergedIntoNames[$mergedId] = Enterprise::query()->whereKey($mergedId)->value('name');
        }

        $name = $this->mergedIntoNames[$mergedId];

        return $name ? 'Fusionada en '.$name : 'Archivada';
    }

    private function ensureProtocol($url)
    {
        if (! preg_match('~^(?:f|ht)tps?://~i', $url))
        {
            $url = 'https://'.$url;
        }

        return $url;
    }
}
