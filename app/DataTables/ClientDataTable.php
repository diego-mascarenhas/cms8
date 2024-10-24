<?php

namespace App\DataTables;

use App\Models\Enterprise;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use Carbon\Carbon;

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
            ->editColumn('name', function ($row) {
                $responsibleName = $row->responsible ? $row->responsible->name : 'Sin responsable asignado';
                return '<div class="d-flex flex-column">
                            <span class="fw-medium text-body text-truncate">' . e($responsibleName) . '</span>
                            <small class="text-muted">' . e($row->name) . '</small>
                        </div>';
            })
            ->editColumn('status_id', function ($row) {
                return $row->status_label;
            })
            ->editColumn('website', function ($row) {
                if ($row->website) {
                    $url = $this->ensureProtocol($row->website);
                    return '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . e($row->website) . '</a>';
                }
                return 'N/A';
            })
            ->editColumn('phone', function ($row) {
                if ($row->phone) {
                    $phoneNumber = preg_replace('/[^0-9+]/', '', $row->phone);
                    return '<a href="tel:' . e($phoneNumber) . '">' . e($row->phone) . '</a>';
                }
                return 'N/A';
            })
            ->rawColumns(['name', 'action', 'current_sentiment', 'social_networks', 'status_id', 'website', 'phone']);
    }

    public function query(Enterprise $model): QueryBuilder
    {
        // $user = auth()->user();

        // $query = $model->clients()->with('status');

        // if ($user->can('client.list'))
        // {
        //     return $query;
        // }
        // elseif ($user->hasRole('colab'))
        // {
        //     return $query->where('assigned_to', $user->id);
        // }
        // else
        // {
        //     return $query->whereRaw('1 = 0');
        // }

        return $model->newQuery()->with('status', 'responsible');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('client-table')
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
                        $('#client-table').DataTable().columns('.select-filter').search($(this).val()).draw();
                    });
                }",
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')->title('Cliente'),
            Column::make('phone')
                ->title('Teléfono')
                ->className('text-center')
                ->searchable(true)
                ->orderable(true)
                ->exportable(true)
                ->printable(true),
            Column::make('website')
                ->title('Sitio Web')
                ->className('text-center')
                ->searchable(true)
                ->orderable(true)
                ->exportable(true)
                ->printable(true),
            Column::make('locality')->title('Ciudad')->className('text-center'),
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
        return 'Client_' . date('YmdHis');
    }

    private function ensureProtocol($url)
    {
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = "https://" . $url;
        }
        return $url;
    }
}
