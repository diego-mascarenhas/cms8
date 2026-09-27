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
            ->setRowId('id')
            ->editColumn('subject', function (Ticket $ticket)
            {
                $creator = e($ticket->user?->name ?? '—');

                return '<a href="'.route('ticket.show', $ticket->id).'" class="text-body">'.e($ticket->subject).'</a>'
                    .'<div class="text-muted small mt-1">'.$creator.'</div>';
            })
            ->editColumn('status', function (Ticket $ticket)
            {
                return '<span class="badge bg-'.$ticket->status_color.'">'.$ticket->status_label.'</span>';
            })
            ->editColumn('priority', function (Ticket $ticket)
            {
                return '<span class="badge bg-'.$ticket->priority_color.'">'.$ticket->priority_label.'</span>';
            })
            ->rawColumns(['subject', 'status', 'priority'])
            ->filterColumn('subject', function (QueryBuilder $query, $keyword)
            {
                $query->where(function (QueryBuilder $inner) use ($keyword)
                {
                    $inner->where('subject', 'like', "%{$keyword}%")
                        ->orWhereHas('user', function ($userQuery) use ($keyword)
                        {
                            $userQuery->where('name', 'like', "%{$keyword}%");
                        });
                });
            });
    }

    public function query(Ticket $model): QueryBuilder
    {
        return $model->newQuery()
            ->with(['user']);
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
            ->parameters([
                'select' => false,
            ])
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json']);
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
        ];
    }

    protected function filename(): string
    {
        return 'Ticket_'.date('YmdHis');
    }
}
