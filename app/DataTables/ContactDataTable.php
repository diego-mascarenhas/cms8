<?php

namespace App\DataTables;

use App\Models\Contact;
use App\Models\Source;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use Carbon\Carbon;

class ContactDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'contact.action')
            ->setRowId('id')
            ->editColumn('name', function ($row) {
                $emailValue = $row->email;

                $contactInfo = $emailValue ? '<a href="mailto:' . $emailValue . '">' . $emailValue . '</a>' : '&nbsp;';

                return '<div class="d-flex flex-column">
                            <span class="fw-medium text-body text-truncate">' . $row->name . '</span>
                            <small class="text-muted">' . $contactInfo . '</small>
                        </div>';
            })
            ->addColumn('current_sentiment', function ($row) {
                if ($row->currentSentiment) {
                    return $row->currentSentiment->sentiment->emoji;
                }
                return '🤔';
            })
            ->filterColumn('current_sentiment', function($query, $keyword) {
                if ($keyword !== '') {
                    $query->whereHas('currentSentiment', function($q) use ($keyword) {
                        $q->where('sentiment_id', $keyword);
                    });
                }
            })
            ->addColumn('sources', function ($row) {
                return $row->sources_icons_html;
            })
            ->addColumn('responsible_name', function ($contact) {
                return $contact->responsible->name ?? 'N/A';
            })
            ->filterColumn('responsible_name', function($query, $keyword) {
                $query->whereHas('responsible', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            })
            ->editColumn('status_id', function ($row) {
                return $row->status_label;
            })
            ->rawColumns(['name', 'action', 'current_sentiment', 'sources', 'status_id']);
    }

    public function query(Contact $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('contact-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'asc')
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
                        $('#contact-table').DataTable().columns('.select-filter').search($(this).val()).draw();
                    });
                }",
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')->title('Nombre'),
            Column::make('current_sentiment')
                ->title('Sentimiento')
                ->className('text-center')
                ->addClass('select-filter')
                ->searchable(true)
                ->orderable(false)
                ->width(150),
            Column::make('sources')
                ->title('Redes')
                ->className('text-center')
                ->searchable(false)
                ->orderable(false)
                ->width(150),
            Column::make('responsible_name')->title('Asesor')->className('text-center'),
            Column::make('status_id')->title('Estado')->className('text-center'),
            Column::computed('action')->title('Acciones')->width(20)->className('text-center')
                ->exportable(false)
                ->printable(false)
                ->width(30)
                ->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Contact_' . date('YmdHis');
    }
}