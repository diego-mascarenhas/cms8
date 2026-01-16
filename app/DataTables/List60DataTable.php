<?php

namespace App\DataTables;

use App\Models\List60;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class List60DataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($row)
            {
                return view('list60.action', [
                    'id' => $row->id,
                    'contact_id' => $row->contact_id,
                    'responsible_id' => $row->responsible_id,
                ]);
            })
            ->setRowId('id')
            ->editColumn('contact_id', function ($row)
            {
                // Mostrar la primera empresa asociada al contacto (si existe)
                $companyName = $row->contact->enterprises->first() ? e($row->contact->enterprises->first()->name) : '';

                return '<div class="d-flex flex-column">
							<span class="fw-medium text-body text-truncate">'.e($row->contact->name).'</span>
							<small class="text-muted">'.($companyName ?: '&nbsp;').'</small>
						</div>';
            })
            ->editColumn('status_id', function ($row)
            {
                return $row->status_label;
            })
            ->addColumn('responsible_name', function ($row)
            {
                return $row->responsible?->name ?? __('Unassigned');
            })
            ->addColumn('sources', function ($row)
            {
                return $row->contact->sources_icons_html;
            })
            ->editColumn('date_next', function ($row)
            {
                return Carbon::parse($row->date_next)->translatedFormat('d F');
            })
            ->editColumn('type_id', function ($row)
            {
                return $row->type->name ?? __('Undefined');
            })
            ->filterColumn('contact_id', function ($query, $keyword)
            {
                $query->whereHas('contact', function ($q) use ($keyword)
                {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            })
            ->rawColumns(['name', 'action', 'contact_id', 'sources', 'status_id']);
    }

    public function query(List60 $model): QueryBuilder
    {
        $query = $model->newQuery();

        if (! auth()->user()->hasRole('admin'))
        {
            $query->myResponsibilities();
        }

        return $query->whereHas('contact')
            ->with([
                'contact.enterprises',
                'contact.sources',
                'contact.status',
                'contact.user.roles',
                'contact.user.teams',
                'status',
                'type',
                'responsible:id,name',
            ]);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('contact-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(4, direction: 'asc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
            ->parameters([
                'pageLength' => 60,
                'paging' => false,
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('contact_id')
                ->title(value: __('Name'))
                ->addClass('all')
                ->orderable(false),
            Column::make('status_id')
                ->title(__('Status'))
                ->className('text-center')
                ->addClass('min-phone'),
            Column::make('sources')
                ->title(__('Networks'))
                ->className('text-center')
                ->addClass('min-desktop')
                ->searchable(false)
                ->orderable(false)
                ->width(150),
            Column::make('date_next')
                ->title(__('Next contact'))
                ->className('text-center')
                ->addClass('min-phone'),
            Column::make('type_id')
                ->title(__('Type'))
                ->className('text-center')
                ->addClass('min-desktop'),
            Column::make('responsible_name')
                ->title(__('Responsible'))
                ->className('text-center')
                ->addClass('min-desktop')
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
        return 'List60_'.date('YmdHis');
    }
}
