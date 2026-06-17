<?php

namespace App\DataTables;

use App\Enums\TransactionType;
use App\Models\Payment;
use App\Support\DataTableFormatter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class IncomeDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'income.action')
            ->setRowId('id')
            ->rawColumns(['action', 'status', 'invoice_id', 'enterprise_id', 'account_id', 'type_id', 'amount'])
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
                if (! $data->enterprise)
                {
                    return '<span class="text-muted">-</span>';
                }

                return DataTableFormatter::showLink(
                    $data,
                    'payments.show',
                    $data->enterprise->name,
                    'view',
                    [$data->id],
                    'fw-medium text-body',
                );
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
                return '<span class="text-success fw-bold">+ '.number_format($data->amount, 2, ',', '.').'</span>';
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
            ->where('transaction_type', TransactionType::INCOME)
            ->with(['enterprise', 'invoice', 'account', 'type']);
    }

    public function html(): HtmlBuilder
    {
        return $this
            ->builder()
            ->setTableId('income-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'desc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'select' => false,
                'autoWidth' => false,
                'drawCallback' => 'function() {
					$("#income-table tbody tr").css({
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
            Column::make('date')
                ->title(__('Date'))
                ->addClass('all')
                ->className('text-center'),
            Column::make('enterprise_id')
                ->title(__('Enterprise'))
                ->addClass('all')
                ->searchable(true)
                ->orderable(false),
            Column::make('invoice_id')
                ->title(__('Invoice'))
                ->addClass('min-tablet'),
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
        return 'Income_'.date('YmdHis');
    }
}
