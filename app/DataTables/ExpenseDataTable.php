<?php

namespace App\DataTables;

use App\Enums\TransactionType;
use App\Models\Payment;
use App\Support\DataTableFormatter;
use App\Support\PaymentTableAmountFormatter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ExpenseDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'expense.action')
            ->setRowId('id')
            ->rawColumns(['action', 'status', 'invoice_id', 'enterprise_id', 'account_id', 'type_id', 'amount'])
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

                $documentNumber = $this->extractDocumentNumberFromRemarks((string) ($data->remarks ?? ''));
                $user = auth()->user();
                $canLinkInvoice = $user && $user->hasAnyRole(['admin', 'collaborator']);

                if (filled($documentNumber))
                {
                    if ($canLinkInvoice)
                    {
                        return '<a href="'.e(route('payments.link-invoice', $data->id)).'" class="text-body" title="'.e(__('payment_invoice.link.action_title')).'">'
                            .e($documentNumber)
                            .'</a>';
                    }

                    return '<span class="text-body">'.e($documentNumber).'</span>';
                }

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
                $query->where(function ($innerQuery) use ($keyword)
                {
                    $innerQuery->whereHas('invoice', function ($invoiceQuery) use ($keyword)
                    {
                        $invoiceQuery->whereRaw('number LIKE ?', ["%{$keyword}%"]);
                    })->orWhereRaw('remarks LIKE ?', ["%Número de documento: {$keyword}%"])
                        ->orWhereRaw('remarks LIKE ?', ["%Document number: {$keyword}%"]);
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
                return $data->account?->name ?? '<span class="text-muted">-</span>';
            })
            ->editColumn('type_id', function ($data)
            {
                return $data->type?->name ?? '<span class="text-muted">-</span>';
            })
            ->editColumn('amount', function ($data)
            {
                return PaymentTableAmountFormatter::formatExpense(
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
        return $model
            ->newQuery()
            ->where('transaction_type', TransactionType::EXPENSE)
            ->with([
                'enterprise',
                'invoice',
                'type',
                'account' => fn ($query) => $query->withoutGlobalScope('activeStatus')->with('currency'),
            ]);
    }

    public function html(): HtmlBuilder
    {
        return $this
            ->builder()
            ->setTableId('expense-table')
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
					$("#expense-table tbody tr").css({
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
                ->addClass('min-tablet')
                ->className('text-center'),
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
        return 'Expense_'.date('YmdHis');
    }

    private function extractDocumentNumberFromRemarks(string $remarks): ?string
    {
        if ($remarks === '')
        {
            return null;
        }

        $patterns = [
            '/Número de documento:\s*([^|]+)/u',
            '/Document number:\s*([^|]+)/u',
        ];

        foreach ($patterns as $pattern)
        {
            if (preg_match($pattern, $remarks, $matches) === 1)
            {
                $documentNumber = trim((string) ($matches[1] ?? ''));
                if ($documentNumber !== '')
                {
                    return $documentNumber;
                }
            }
        }

        return null;
    }
}
