<?php

namespace App\DataTables;

use App\Models\List60;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use Carbon\Carbon;

class List60DataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'list60.action')
            ->setRowId('id')
            ->editColumn('contact_id', function ($row) {
                $emailValue = $row->contact->email ?? null;

                $contactInfo = $emailValue ? '<a href="mailto:' . $emailValue . '">' . $emailValue . '</a>' : '&nbsp;';

                return '<div class="d-flex flex-column">
                            <span class="fw-medium text-body text-truncate">' . $row->contact->name . '</span>
                            <small class="text-muted">' . $contactInfo . '</small>
                        </div>';
            })
            ->editColumn('status_id', function ($row) {
                return $row->status_label;
            })
            ->addColumn('sources', function ($row) {
                return $row->contact->sources_icons_html;
            })
            ->editColumn('date_next', function ($row) {
                return \Carbon\Carbon::parse($row->date_next)->translatedFormat('d F');
            })
            ->editColumn('type_id', function ($row) {
                return $row->type->name ?? 'Sin definir';
            })
            ->filterColumn('contact_id', function($query, $keyword) {
                $query->whereHas('contact', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            })
            ->rawColumns(['name', 'action', 'contact_id', 'sources', 'status_id']);
    }

    public function query(List60 $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('contact-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(4, direction: 'asc')
            ->language(['url' => '/js/datatables/' . session()->get('locale', app()->getLocale()) . '.json'])
            ->parameters([
                'pageLength' => 60,
                'paging' => false
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('contact_id')->title(value: 'Nombre')->orderable(false),
            Column::make('status_id')->title('Estado')->className('text-center'),
            Column::make('sources')
                ->title('Redes')
                ->className('text-center')
                ->searchable(false)
                ->orderable(false)
                ->width(150),
            Column::make('date_next')->title('Próximo contacto')->className('text-center'),
            Column::make('type_id')->title('Tipo')->className('text-center'),
            Column::computed('action')->title('Acciones')->width(20)->className('text-center')
                ->exportable(false)
                ->printable(false)
                ->width(30)
                ->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'List60_' . date('YmdHis');
    }
}