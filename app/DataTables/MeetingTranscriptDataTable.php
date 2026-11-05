<?php

namespace App\DataTables;

use App\Enums\MeetingTranscriptStatus;
use App\Models\MeetingTranscript;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class MeetingTranscriptDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->setRowId('id')
            ->editColumn('title', function (MeetingTranscript $meeting)
            {
                return '<a href="'.route('meeting.show', $meeting).'" class="text-body">'.e($meeting->title).'</a>'
                    .'<div class="text-muted small">'.e($meeting->original_filename).'</div>';
            })
            ->editColumn('status', function (MeetingTranscript $meeting)
            {
                return '<span class="badge bg-label-'.$this->statusColor($meeting->status).'">'.$this->statusLabel($meeting->status).'</span>';
            })
            ->editColumn('created_at', function (MeetingTranscript $meeting)
            {
                return $meeting->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '';
            })
            ->addColumn('action', function (MeetingTranscript $meeting)
            {
                return view('meeting.action', ['meeting' => $meeting])->render();
            })
            ->rawColumns(['title', 'status', 'action'])
            ->orderColumn('created_at', 'created_at $1');
    }

    public function query(MeetingTranscript $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('meeting-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(2, 'desc')
            ->responsive(true)
            ->parameters([
                'select' => false,
                'autoWidth' => false,
                'language' => [
                    'emptyTable' => 'Todavía no hay reuniones.',
                    'zeroRecords' => 'Todavía no hay reuniones.',
                    'url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json',
                ],
                'drawCallback' => 'function() {
                    $("#meeting-table tbody tr").css({
                        "user-select": "none",
                        "-webkit-user-select": "none",
                        "-moz-user-select": "none",
                        "-ms-user-select": "none"
                    });
                }',
            ]);
    }

    /**
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return [
            Column::make('title')->title('Reunión')->addClass('all'),
            Column::make('status')->title('Estado')->className('text-center')->addClass('min-tablet'),
            Column::make('created_at')->title('Fecha')->addClass('min-tablet'),
            Column::computed('action')
                ->title('')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->addClass('all text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Meeting_'.date('YmdHis');
    }

    private function statusLabel(MeetingTranscriptStatus $status): string
    {
        return match ($status)
        {
            MeetingTranscriptStatus::Pending => 'Pendiente',
            MeetingTranscriptStatus::Processing => 'Transcribiendo',
            MeetingTranscriptStatus::Ready => 'Lista',
            MeetingTranscriptStatus::Failed => 'Falló',
        };
    }

    private function statusColor(MeetingTranscriptStatus $status): string
    {
        return match ($status)
        {
            MeetingTranscriptStatus::Pending => 'warning',
            MeetingTranscriptStatus::Processing => 'info',
            MeetingTranscriptStatus::Ready => 'success',
            MeetingTranscriptStatus::Failed => 'danger',
        };
    }
}
