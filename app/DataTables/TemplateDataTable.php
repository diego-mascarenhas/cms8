<?php

namespace App\DataTables;

use App\Models\Template;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class TemplateDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($template)
            {
                return view('template.action', ['hashedId' => $template->getHashedId()])->render();
            })
            ->setRowId('id')
            ->rawColumns(['name', 'action', 'status_id'])
            ->editColumn('updated_at', function ($data)
            {
                return Carbon::parse($data->updated_at)->format('d-m-Y H:i:s');
            })
            ->editColumn('status_id', function ($data)
            {
                if ($data->status_id)
                {
                    return '<span class="badge rounded-pill bg-label-success">'.__('Active').'</span>';
                } else
                {
                    return '<span class="badge rounded-pill bg-label-warning">'.__('Inactive').'</span>';
                }
            });
    }

    public function query(Template $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('template-table')
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
                ->title(__('Name'))
                ->addClass('all'),
            Column::make('updated_at')
                ->title(__('Updated'))
                ->className('text-center')
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
        return 'Template_'.date('YmdHis');
    }
}
