<?php

namespace App\DataTables;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class PaymentDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'payments.action')
            ->setRowId('id')
            ->rawColumns(['status', 'invoice_id', 'enterprise_id', 'account_id', 'type_id', 'transaction_indicator'])
            ->addColumn('transaction_indicator', function ($data)
            {
                return $data->transaction_type?->badge() ?? '';
            })
            ->editColumn('date', function ($data)
            {
                return Carbon::parse($data->date)->format('d/m/Y');
            })
            ->editColumn('invoice_id', function ($data)
            {
                return $data->invoice?->number ?? '<span class="text-muted">-</span>';
            })
            ->filterColumn('invoice_id', function ($query, $keyword)
            {
                $query->whereHas('invoice', function ($q) use ($keyword)
                {
                    $q->whereRaw('number LIKE ?', ["%{$keyword}%"]);
                });
            })
            ->editColumn('enterprise_id', function ($data)
            {
                return $data->enterprise?->name ?? '<span class="text-muted">-</span>';
            })
            ->filterColumn('enterprise_id', function ($query, $keyword)
            {
                $query->whereHas('enterprise', function ($q) use ($keyword)
                {
                    $q->whereRaw('name LIKE ?', ["%{$keyword}%"]);
                });
            })
            ->editColumn('account_id', function ($data)
            {
                return $data->account?->name ?? '<span class="text-muted">-</span>';
            })
            ->editColumn('type_id', function ($data)
            {
                return $data->type?->name ?? '<span class="text-muted">-</span>';
            })
            ->editColumn('amount', function ($data)
            {
                return number_format($data->amount, 2, ',', '.');
            })
            ->editColumn('status', function ($data)
            {
                return $data->status_label;
            });
    }

    public function query(Payment $model): QueryBuilder
    {
        return $model
            ->newQuery()
            ->with(['enterprise', 'invoice', 'account', 'type']);
    }

    public function html(): HtmlBuilder
    {
        return $this
            ->builder()
            ->setTableId('payment-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(2, 'desc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
            ->parameters([
                'select' => false,
                'autoWidth' => false,
                'drawCallback' => 'function() {
					$("#payment-table tbody tr").css({
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
            Column::computed('transaction_indicator')
                ->title('')
                ->addClass('all')
                ->width(30)
                ->searchable(false)
                ->orderable(false)
                ->className('text-center'),
            Column::make('date')
                ->title(__('Date'))
                ->addClass('min-tablet')
                ->className('text-center'),
            Column::make('invoice_id')
                ->title(__('Invoice'))
                ->addClass('min-tablet'),
            Column::make('enterprise_id')
                ->title(__('Enterprise'))
                ->addClass('all')
                ->searchable(true)
                ->orderable(false),
            Column::make('account_id')
                ->title(__('Account'))
                ->addClass('min-desktop'),
            Column::make('type_id')
                ->title(__('Type'))
                ->addClass('min-desktop'),
            Column::make('amount')
                ->title(__('Amount'))
                ->addClass('min-tablet')
                ->className('text-end'),
            Column::make('status')
                ->title(__('Status'))
                ->addClass('min-phone')
                ->className('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Payment_'.date('YmdHis');
    }
}
