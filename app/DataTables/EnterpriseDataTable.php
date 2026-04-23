<?php

namespace App\DataTables;

use App\Models\Enterprise;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class EnterpriseDataTable extends DataTable
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
                $firstContact = $row->contacts->first();
                $contactId = $firstContact ? $firstContact->id : null;

                return view('enterprise.action', [
                    'enterprise' => $row,
                    'contactId' => $contactId,
                ])->render();
            })
            ->setRowId('id')
            ->editColumn('name', function ($row)
            {
                return '<div class="d-flex flex-column">
							<span class="fw-medium text-body text-truncate">'.e($row->name).'</span>
							<small class="text-muted">'.e($row->email ?? '-').'</small>
						</div>';
            })
            ->addColumn('billing_addresses', function ($row)
            {
                // Obtener solo la dirección de facturación activa
                $activeAddress = $row->enterpriseBillingAddresses->where('status', 1)->first();
                
                if (!$activeAddress)
                {
                    return '<span class="text-muted">-</span>';
                }

                return '<div class="d-flex flex-column">
							<span class="fw-medium text-body">'.e($activeAddress->name).'</span>
							<small class="text-muted">'.e($activeAddress->identification_number ?? '-').'</small>
						</div>';
            })
            ->editColumn('status_id', function ($row)
            {
                return $row->status_label;
            })
            ->rawColumns(['name', 'action', 'billing_addresses', 'status_id']);
    }

    public function query(Enterprise $model): QueryBuilder
    {
        return $model->newQuery()
            ->select([
                'enterprises.id',
                'enterprises.name',
                'enterprises.email',
                'enterprises.responsible_id',
                'enterprises.status_id',
                'enterprises.team_id',
                'enterprises.type_id',
            ])
            ->with([
                'status:id,name,label_class',
                'enterpriseBillingAddresses:id,enterprise_id,name,identification_number,status',
                'contacts:id',
            ]);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('enterprise-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
            ->parameters([
                'select' => false,
                'autoWidth' => false,
                'drawCallback' => 'function() {
                    $("#enterprise-table tbody tr").css({
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
            Column::make('name')
                ->title(__('Enterprise'))
                ->addClass('all'),
            Column::make('billing_addresses')
                ->title(__('Billing Addresses'))
                ->className('text-start')
                ->addClass('min-desktop')
                ->searchable(false)
                ->orderable(false)
                ->width(300),
            Column::make('status_id')
                ->title(__('Status'))
                ->className('text-center')
                ->addClass('min-tablet'),
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
        return 'Enterprise_'.date('YmdHis');
    }
}
