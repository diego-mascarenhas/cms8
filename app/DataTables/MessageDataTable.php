<?php

namespace App\DataTables;

use App\Models\Message;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class MessageDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'message.action')
            ->setRowId('id')
            ->rawColumns(['name', 'action', 'status_id', 'category_info', 'progress'])
            ->editColumn('name', function ($data)
            {
                $typeBadge = '<span class="badge bg-label-primary rounded-pill mt-1">'.$data->type->name.'</span>';

                return '<div class="d-flex flex-column">
                    <span class="fw-semibold">'.$data->name.'</span>
                    <div class="mt-1">'.$typeBadge.'</div>
                </div>';
            })
            ->addColumn('category_info', function ($data)
            {
                $categoryName = optional($data->category)->name ?? '<span class="text-muted">Sin categoría</span>';
                $contactStatus = optional($data->contactStatus)->name ?? '<span class="text-muted">Todos</span>';

                return '<div class="d-flex flex-column">
                    <span>'.$categoryName.'</span>
                    <small class="text-muted mt-1">'.$contactStatus.'</small>
                </div>';
            })
            ->addColumn('progress', function ($data)
            {
                // Get delivery stats
                $total = $data->deliveries_count ?? 0;

                if ($total === 0)
                {
                    return '<div class="text-muted small">Sin envíos</div>';
                }

                $sent = $data->sent_count ?? 0;
                $delivered = $data->delivered_count ?? 0;
                $opened = $data->opened_count ?? 0;

                // Calculate percentages
                $sentPercent = $total > 0 ? ($sent / $total) * 100 : 0;
                $deliveredPercent = $total > 0 ? ($delivered / $total) * 100 : 0;
                $openedPercent = $total > 0 ? ($opened / $total) * 100 : 0;
                $openRate = $delivered > 0 ? ($opened / $delivered) * 100 : 0;

                return '
                    <div class="mb-1">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">Progreso de Campaña</span>
                            <span class="small fw-semibold">'.number_format($openRate, 2).'% Tasa de Apertura</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-warning" style="width: '.min(100, $openedPercent).'%"></div>
                            <div class="progress-bar bg-info" style="width: '.min(100 - $openedPercent, $deliveredPercent - $openedPercent).'%"></div>
                            <div class="progress-bar bg-success" style="width: '.min(100 - $deliveredPercent, $sentPercent - $deliveredPercent).'%"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-warning">'.number_format($openedPercent, 1).'% Abierto</small>
                            <small class="text-info">'.number_format($deliveredPercent, 1).'% Entregado</small>
                            <small class="text-success">'.number_format($sentPercent, 1).'% Enviado</small>
                        </div>
                    </div>
                ';
            })
            ->editColumn('status_id', function ($data)
            {
                $statusValue = is_object($data->status_id) ? $data->status_id->value : $data->status_id;

                if ($statusValue == 2)
                {
                    return '<span class="badge rounded-pill bg-label-success">'.__('Active').'</span>';
                } else
                {
                    return '<span class="badge rounded-pill bg-label-warning">'.__('Inactive').'</span>';
                }
            });
    }

    public function query(Message $model): QueryBuilder
    {
        return $model->newQuery()
            ->with([
                'type',
                'category',
                'contactStatus',
                'deliveries',
            ])
            ->withCount([
                'deliveries',
                'deliveries as sent_count' => function ($query)
                {
                    $query->whereNotNull('sent_at');
                },
                'deliveries as delivered_count' => function ($query)
                {
                    $query->whereNotNull('delivered_at');
                },
                'deliveries as opened_count' => function ($query)
                {
                    $query->whereNotNull('opened_at');
                },
            ]);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('message-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1)
            ->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json']);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')
                ->title(__('Subject'))
                ->addClass('all'),
            Column::computed('category_info')
                ->title(__('Category'))
                ->orderable(false)
                ->searchable(false)
                ->addClass('min-desktop'),
            Column::computed('progress')
                ->title(__('Campaign Progress'))
                ->orderable(false)
                ->searchable(false)
                ->addClass('min-tablet'),
            Column::make('status_id')
                ->title(__('Status'))
                ->className('text-center')
                ->addClass('min-tablet'),
            Column::computed('action')
                ->title(__('Actions'))
                ->width(20)
                ->className('text-center')
                ->exportable(false)
                ->printable(false)
                ->width(30)
                ->addClass('min-desktop'),
        ];
    }

    protected function filename(): string
    {
        return 'Message_'.date('YmdHis');
    }
}
