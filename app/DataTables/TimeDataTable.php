<?php

namespace App\DataTables;

use App\Models\Time;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class TimeDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'time.action')
            ->setRowId('id')
            ->editColumn('user_id', function ($data)
            {
                return $data->user?->name ?? '<span class="text-muted">N/A</span>';
            })
            ->filterColumn('user_id', function ($query, $keyword)
            {
                $query->whereHas('user', function ($q) use ($keyword)
                {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            })
            ->editColumn('task_id', function ($data)
            {
                return $data->task ? $data->task->title : '<span class="text-muted">-</span>';
            })
            ->filterColumn('task_id', function ($query, $keyword)
            {
                $query->whereHas('task', function ($q) use ($keyword)
                {
                    $q->whereRaw('title LIKE ?', ["%{$keyword}%"]);
                });
            })
            ->editColumn('start_time', function ($data)
            {
                return $data->start_time ? Carbon::parse($data->start_time)->format('d-m-Y H:i') : '-';
            })
            ->editColumn('end_time', function ($data)
            {
                if (! $data->end_time)
                {
                    return '<span class="badge bg-label-success">Running</span>';
                }

                return Carbon::parse($data->end_time)->format('d-m-Y H:i');
            })
            ->addColumn('duration', function ($data)
            {
                if ($data->isRunning())
                {
                    $elapsed = max(0, now()->diffInSeconds($data->start_time));
                    $hours = floor($elapsed / 3600);
                    $minutes = floor(($elapsed % 3600) / 60);

                    return sprintf(
                        '<span class="timer-running" data-start="%s">%dh %dm</span>',
                        $data->start_time->timestamp,
                        $hours,
                        $minutes,
                    );
                }

                return $data->formatted_duration;
            })
            ->addColumn('earnings', function ($data)
            {
                if (! $data->is_billable)
                {
                    return '<span class="text-muted">-</span>';
                }

                return '$'.number_format($data->earnings, 2);
            })
            ->editColumn('is_billable', function ($data)
            {
                return $data->is_billable
                    ? '<span class="badge bg-label-success">Yes</span>'
                    : '<span class="badge bg-label-secondary">No</span>';
            })
            ->rawColumns(['action', 'task_id', 'end_time', 'duration', 'earnings', 'is_billable', 'user_id']);
    }

    public function query(Time $model): QueryBuilder
    {
        return $model
            ->newQuery()
            ->with(['user:id,name', 'task:id,title'])
            ->orderBy('start_time', 'desc');
    }

    public function html(): HtmlBuilder
    {
        return $this
            ->builder()
            ->setTableId('time-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(2, 'desc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
            ->parameters([
                'select' => false,
                'autoWidth' => false,
                'drawCallback' => 'function() {
					$("#time-table tbody tr").css({
						"user-select": "none",
						"-webkit-user-select": "none",
						"-moz-user-select": "none",
						"-ms-user-select": "none"
					});
				}',
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('user_id')
                ->title(__('User'))
                ->addClass('min-tablet')
                ->searchable(true)
                ->orderable(false),
            Column::make('task_id')
                ->title(__('Task'))
                ->addClass('min-tablet')
                ->searchable(true)
                ->orderable(false),
            Column::make('start_time')
                ->title(__('Start'))
                ->addClass('min-phone')
                ->searchable(false)
                ->orderable(true),
            Column::make('end_time')
                ->title(__('End'))
                ->addClass('min-desktop')
                ->searchable(false)
                ->orderable(false),
            Column::computed('duration')
                ->title(__('Duration'))
                ->className('text-center')
                ->addClass('min-phone')
                ->searchable(false)
                ->orderable(false),
            Column::make('description')
                ->title(__('Description'))
                ->addClass('min-desktop')
                ->searchable(true)
                ->orderable(false),
            Column::computed('earnings')
                ->title(__('Earnings'))
                ->className('text-end')
                ->addClass('min-desktop')
                ->searchable(false)
                ->orderable(false),
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
        return 'Time_'.date('YmdHis');
    }
}
