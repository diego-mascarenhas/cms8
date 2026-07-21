<?php

namespace App\DataTables;

use App\Models\Automation;
use App\Support\DataTableFormatter;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AutomationDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('name', function (Automation $automation)
            {
                return DataTableFormatter::showLink($automation, 'automation.show', $automation->name, 'view', [$automation]);
            })
            ->editColumn('is_active', function (Automation $automation)
            {
                return $automation->is_active
                    ? '<span class="badge rounded-pill bg-label-success">'.e(__('Activo')).'</span>'
                    : '<span class="badge rounded-pill bg-label-secondary">'.e(__('Inactivo')).'</span>';
            })
            ->addColumn('channels_summary', function (Automation $automation)
            {
                $channels = is_array($automation->channels) ? $automation->channels : [];
                $enabled = [];
                foreach ($channels as $key => $on)
                {
                    if ($on)
                    {
                        $enabled[] = e((string) $key);
                    }
                }

                return $enabled !== []
                    ? implode(', ', $enabled)
                    : '<span class="text-muted">—</span>';
            })
            ->addColumn('action', function (Automation $automation)
            {
                return view('automation.action', compact('automation'));
            })
            ->rawColumns(['name', 'is_active', 'channels_summary', 'action'])
            ->setRowId('id');
    }

    public function query(Automation $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('automation-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(0, 'desc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'select' => false,
            ]);
    }

    /**
     * @return list<Column>
     */
    protected function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')->title(__('Nombre'))->searchable(true)->orderable(true),
            Column::make('slug')->title(__('Slug'))->searchable(true)->orderable(true),
            Column::make('entry_prompt_key')->title(__('Prompt'))->searchable(true)->orderable(true),
            Column::computed('channels_summary')->title(__('Canales'))->searchable(false)->orderable(false),
            Column::make('is_active')->title(__('Activo'))->orderable(true)->className('text-center'),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center')
                ->title(__('Acciones')),
        ];
    }

    protected function filename(): string
    {
        return 'Automations_'.date('YmdHis');
    }
}
