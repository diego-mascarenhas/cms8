<?php

namespace App\DataTables;

use App\Models\Message;
use App\Support\DataTableFormatter;
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
                return DataTableFormatter::link(
                    route('message.show', $data->id),
                    $data->name,
                    'fw-semibold',
                );
            })
            ->addColumn('category_info', function ($data)
            {
                $categoryLine = $data->hasContactCategoryFilter()
                    ? e($data->contactCategories->sortBy('name')->pluck('name')->implode(', '))
                    : '<span class="text-muted">'.e(__('app.message_form_categories_all')).'</span>';
                $contactStatus = optional($data->contactStatus)->name
                    ?? '<span class="text-muted">'.e(__('app.message_form_contact_status_all')).'</span>';

                return '<div class="d-flex flex-column">
                    <span>'.$categoryLine.'</span>
                    <small class="text-muted mt-1">'.$contactStatus.'</small>
                </div>';
            })
            ->addColumn('progress', function ($data)
            {
                // Get delivery stats
                $total = $data->deliveries_count ?? 0;

                if ($total === 0)
                {
                    return $this->progressColumnNoDeliveriesHtml($data);
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
                return $this->messageListStatusBadgeHtml($data);
            });
    }

    /**
     * Badge for the messages list: scheduled (future send), sending (active), or paused.
     */
    private function messageListStatusBadgeHtml(Message $message): string
    {
        $raw = (int) ($message->getRawOriginal('status_id') ?? 0);
        $isActive = $raw === 1 || $raw === 2;

        $scheduledAt = $message->scheduled_send_at;
        $isScheduledFuture = $scheduledAt !== null && $scheduledAt->isFuture();

        if ($isScheduledFuture)
        {
            $label = e(__('app.message_list_status_scheduled'));

            return '<span class="badge rounded-pill bg-label-info">'.$label.'</span>';
        }

        if ($isActive)
        {
            $label = e(__('app.message_list_status_sending'));

            return '<span class="badge rounded-pill bg-label-success">'.$label.'</span>';
        }

        $label = e(__('app.message_list_status_paused'));

        return '<span class="badge rounded-pill bg-label-warning">'.$label.'</span>';
    }

    /**
     * Campaign progress cell when there are no deliveries yet: show schedule hint under "no sends".
     */
    private function progressColumnNoDeliveriesHtml(Message $message): string
    {
        $block = '<div class="text-muted small">'.e(__('app.message_list_no_deliveries')).'</div>';
        $block .= $this->progressScheduleSublineHtml($message);

        return $block;
    }

    private function progressScheduleSublineHtml(Message $message): string
    {
        $at = $message->scheduled_send_at;
        if ($at === null)
        {
            return '<div class="text-muted small mt-1">'.e(__('app.message_list_not_scheduled')).'</div>';
        }

        $formatted = $at->clone()
            ->timezone(config('app.timezone'))
            ->locale(app()->getLocale())
            ->translatedFormat('d M Y H:i');

        return '<div class="text-muted small mt-1">'.e(__('app.message_list_scheduled_at', ['datetime' => $formatted])).'</div>';
    }

    public function query(Message $model): QueryBuilder
    {
        return $model->newQuery()
            ->with([
                'contactCategories',
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
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json']);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')
                ->title(__('Subject'))
                ->addClass('all'),
            Column::computed('category_info')
                ->title(__('app.Tags'))
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
