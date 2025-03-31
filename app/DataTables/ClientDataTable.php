<?php

namespace App\DataTables;

use App\Models\Enterprise;
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
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'client.action')
            ->setRowId('id')
            ->editColumn('name', function ($row)
            {
                return '<div class="d-flex flex-column">
                            <span class="fw-medium text-body text-truncate">' . e($row->name) . '</span>
                            <small class="text-muted">' . e($row->responsible->name ?? 'Sin asignar') . '</small>
                        </div>';
            })
            ->addColumn('current_sentiment', function ($row)
            {
                if ($row->responsible && $row->responsible->currentSentiment)
                {
                    return '<span style="font-size: 1.5em;">' . $row->responsible->currentSentiment->sentiment->emoji . '</span>';
                }
                return '<span style="font-size: 1.5em;">🤔</span>';
            })
            ->addColumn('sources', function ($row)
            {
                return $row->responsible ? $row->responsible->sources_icons_html : '';
            })
            ->addColumn('responsible_name', function ($contact)
            {
                return $contact->responsible->name ?? 'Sin asignar';
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
            ->rawColumns(['name', 'action', 'current_sentiment', 'sources', 'status_id', 'website', 'phone']);
    }

    public function query(Enterprise $model): QueryBuilder
    {
        return $model->newQuery()
            ->activeClients()
            ->with([
                'responsible:id,name',
                'responsible.currentSentiment.sentiment',
                'responsible.sources',
                'status'
            ]);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('client-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/' . session()->get('locale', app()->getLocale()) . '.json'])
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
                        $('#client-table').DataTable().columns('.select-filter').search($(this).val()).draw();
                    });
                }",
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')
                ->title('Cliente')
                ->addClass('all'),
            Column::make('current_sentiment')
                ->title('Sentimiento')
                ->className('text-center')
                ->addClass('select-filter min-tablet')
                ->searchable(true)
                ->orderable(false)
                ->width(150),
            Column::make('sources')
                ->title('Redes')
                ->className('text-center')
                ->addClass('min-desktop')
                ->searchable(false)
                ->orderable(false)
                ->width(150),
            Column::make('responsible_name')
                ->title('Administrador')
                ->className('text-center')
                ->addClass('min-tablet')
                ->searchable(false)
                ->orderable(false),
            Column::make('status_id')
                ->title('Estado')
                ->className('text-center')
                ->addClass('min-tablet'),
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

    protected function filename(): string
    {
        return 'Client_' . date('YmdHis');
    }

    private function ensureProtocol($url)
    {
        if (!preg_match("~^(?:f|ht)tps?://~i", $url))
        {
            $url = "https://" . $url;
        }
        return $url;
    }

    public function scopeActiveClients($query)
    {
        return $query->where('status_id', 2);
    }
}
