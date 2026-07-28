<?php

namespace App\DataTables;

use App\Models\Payment;
use App\Services\Finance\PaymentSummaryService;
use App\Support\DataTableFormatter;
use App\Support\PaymentTableAmountFormatter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class PaymentDataTable extends DataTable
{
    protected ?int $accountId = null;

    public function forAccount(int $accountId): self
    {
        $this->accountId = $accountId;

        return $this;
    }

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
            ->rawColumns(['action', 'status', 'invoice_id', 'enterprise_id', 'account_id', 'type_id', 'transaction_indicator', 'amount'])
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
                if ($data->invoice)
                {
                    return '<a href="'.e(route('invoice.show', $data->invoice->id)).'" class="text-body">'.e($data->invoice->number).'</a>';
                }

                $user = auth()->user();
                if ($user && $user->hasAnyRole(['admin', 'collaborator']))
                {
                    return '<a href="'.e(route('payments.link-invoice', $data->id)).'" class="text-body" title="'.e(__('payment_invoice.link.action_title')).'">'
                        .'<i class="ti ti-link ti-sm"></i>'
                        .'</a>';
                }

                return '<span class="text-muted">-</span>';
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
                    $data->enterprise,
                    'client.show',
                    $data->enterprise->name,
                    'view',
                    [$data->enterprise->id],
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
                if (! $data->account)
                {
                    return '<span class="text-muted">-</span>';
                }

                if (auth()->user()?->can('view', $data->account))
                {
                    return '<a href="'.e(route('payment-account.show', $data->account)).'" class="text-body">'
                        .e($data->account->name)
                        .'</a>';
                }

                return e($data->account->name);
            })
            ->editColumn('type_id', function ($data)
            {
                return $data->type?->name ?? '<span class="text-muted">-</span>';
            })
            ->editColumn('amount', function ($data)
            {
                return PaymentTableAmountFormatter::format(
                    (float) $data->amount,
                    $data->currency_code,
                );
            })
            ->editColumn('status', function ($data)
            {
                return $data->status_label;
            });
    }

    public function query(Payment $model): QueryBuilder
    {
        $query = $model
            ->newQuery()
            ->with([
                'enterprise',
                'invoice',
                'type',
                'account' => fn ($query) => $query->withoutGlobalScope('activeStatus')->with('currency'),
            ]);

        if ($this->accountId !== null)
        {
            $query->where('account_id', $this->accountId);
        }

        return app(PaymentSummaryService::class)->applyStatusFilter(
            $query,
            request()->input('status_filter'),
        );
    }

    public function html(): HtmlBuilder
    {
        $tableId = $this->accountId !== null
            ? 'payment-account-movements-table'
            : 'payment-table';

        $builder = $this
            ->builder()
            ->setTableId($tableId)
            ->columns($this->getColumns())
            ->dom('frtip')
            ->orderBy($this->accountId !== null ? 1 : 2, 'desc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'select' => false,
                'autoWidth' => false,
                'drawCallback' => 'function() {
					$("#'.$tableId.' tbody tr").css({
						"user-select": "none",
						"-webkit-user-select": "none",
						"-moz-user-select": "none",
						"-ms-user-select": "none"
					});
				}',
            ]);

        if ($this->accountId !== null)
        {
            return $builder->minifiedAjax();
        }

        return $builder->minifiedAjax(
            '',
            "data.status_filter = ($('#payment-status-filter').val() || 'all');",
        );
    }

    public function getColumns(): array
    {
        $columns = [
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
                ->addClass('min-tablet')
                ->className('text-center'),
            Column::make('enterprise_id')
                ->title(__('Enterprise'))
                ->addClass('all')
                ->searchable(true)
                ->orderable(false),
        ];

        if ($this->accountId === null)
        {
            $columns[] = Column::make('account_id')
                ->title(__('Account'))
                ->addClass('min-desktop');
        }

        $columns[] = Column::make('type_id')
            ->title(__('Type'))
            ->addClass('min-desktop');
        $columns[] = Column::make('amount')
            ->title(__('Amount'))
            ->addClass('min-tablet')
            ->className('text-end');
        $columns[] = Column::make('status')
            ->title(__('Status'))
            ->addClass('min-phone')
            ->className('text-center');

        return $columns;
    }

    protected function filename(): string
    {
        return 'Payment_'.date('YmdHis');
    }
}
