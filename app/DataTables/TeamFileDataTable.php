<?php

namespace App\DataTables;

use App\Enums\MultimediaVisibility;
use App\Models\TeamFile;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class TeamFileDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function (TeamFile $teamFile)
            {
                return view('team-file.action', ['teamFile' => $teamFile])->render();
            })
            ->editColumn('title', function (TeamFile $teamFile)
            {
                return e($teamFile->title);
            })
            ->editColumn('visibility', function (TeamFile $teamFile)
            {
                $label = $teamFile->visibility?->label() ?? __('Unknown');
                $class = $teamFile->visibility === MultimediaVisibility::PUBLIC ? 'bg-label-info' : 'bg-label-secondary';

                return '<span class="badge rounded-pill '.$class.'">'.$label.'</span>';
            })
            ->addColumn('file_name', function (TeamFile $teamFile)
            {
                $media = $teamFile->getFirstMedia('file');

                return $media ? e($media->file_name) : '<span class="text-muted">—</span>';
            })
            ->editColumn('updated_at', function (TeamFile $teamFile)
            {
                return $teamFile->updated_at
                    ? Carbon::parse($teamFile->updated_at)->format('d-m-Y H:i')
                    : '—';
            })
            ->rawColumns(['visibility', 'file_name', 'action'])
            ->setRowId('id');
    }

    public function query(TeamFile $model): QueryBuilder
    {
        $query = $model->newQuery()->with('media');

        $request = $this->request();
        if ($request->filled('visibility'))
        {
            $query->where('visibility', $request->get('visibility'));
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('team-files-table')
            ->columns($this->getColumns())
            ->minifiedAjax(
                '',
                "data.visibility = $('#filter_team_file_visibility').val();",
            )
            ->dom('frtip')
            ->orderBy(4, 'desc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
            ->parameters([
                'select' => false,
                'lengthChange' => false,
            ]);
    }

    protected function getColumns(): array
    {
        return [
            Column::make('title')->title(__('Title'))->searchable(true)->orderable(true),
            Column::computed('file_name')->title(__('File'))->searchable(false)->orderable(false),
            Column::make('visibility')->title(__('Visibility'))->searchable(false)->orderable(true),
            Column::make('description')->title(__('Description'))->searchable(true)->orderable(false),
            Column::make('updated_at')->title(__('Updated'))->searchable(false)->orderable(true),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(80)
                ->addClass('text-center')
                ->title(__('Actions')),
        ];
    }

    protected function filename(): string
    {
        return 'TeamFiles_'.date('YmdHis');
    }
}
