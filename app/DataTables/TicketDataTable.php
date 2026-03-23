<?php

namespace App\DataTables;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class TicketDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'ticket.action')
            ->setRowId('id')
            ->editColumn('subject', function (Ticket $ticket)
            {
                return '<a href="'.route('ticket.show', $ticket->id).'" class="text-body">'.e($ticket->subject).'</a>';
            })
            ->editColumn('status', function (Ticket $ticket)
            {
                return '<span class="badge bg-'.$ticket->status_color.'">'.$ticket->status_label.'</span>';
            })
            ->editColumn('priority', function (Ticket $ticket)
            {
                return '<span class="badge bg-'.$ticket->priority_color.'">'.$ticket->priority_label.'</span>';
            })
            ->editColumn('user_id', function (Ticket $ticket)
            {
                return $ticket->user?->name ?? '—';
            })
            ->editColumn('assigned_to', function (Ticket $ticket)
            {
                return $ticket->assignedTo?->name ?? '—';
            })
            ->editColumn('created_at', function (Ticket $ticket)
            {
                return $ticket->created_at->format('d/m/Y H:i');
            })
            ->rawColumns(['action', 'subject', 'status', 'priority'])
            ->filterColumn('user_id', function (QueryBuilder $query, $keyword)
            {
                $query->whereHas('user', function ($q) use ($keyword)
                {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('assigned_to', function (QueryBuilder $query, $keyword)
            {
                $query->whereHas('assignedTo', function ($q) use ($keyword)
                {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            });
    }

    public function query(Ticket $model): QueryBuilder
    {
        return $model->newQuery()
            ->with(['user', 'assignedTo']);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('ticket-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(0, 'desc')
            ->responsive(true)
            ->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json']);
    }

    /**
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return [
            Column::make('id')->title('#')->addClass('min-tablet'),
            Column::make('subject')->title(__('tickets.Subject'))->addClass('all'),
            Column::make('status')->title(__('tickets.Status'))->className('text-center')->addClass('min-tablet'),
            Column::make('priority')->title(__('tickets.Priority'))->className('text-center')->addClass('min-tablet'),
            Column::make('user_id')->title(__('tickets.Created by'))->addClass('min-desktop')->orderable(false),
            Column::make('assigned_to')->title(__('tickets.Assigned to'))->addClass('min-desktop')->orderable(false),
            Column::make('created_at')->title(__('tickets.Created'))->addClass('min-desktop'),
            Column::computed('action')
                ->title(__('tickets.Actions'))
                ->className('text-center')
                ->addClass('min-desktop')
                ->exportable(false)
                ->printable(false)
                ->width(80),
        ];
    }

    protected function filename(): string
    {
        return 'Ticket_'.date('YmdHis');
    }
}
