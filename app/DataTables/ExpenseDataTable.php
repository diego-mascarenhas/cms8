<?php

namespace App\DataTables;

use App\Enums\TransactionType;
use App\Models\Payment;
use App\Services\Finance\PaymentReportingCurrencyService;
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
    private string $reportingCurrency;

    public function __construct()
    {
        parent::__construct();
        $this->reportingCurrency = app(PaymentReportingCurrencyService::class)->reportingCurrencyForCurrentTeam();
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->setRowId('id')
            ->rawColumns(['status', 'invoice_id', 'enterprise_id', 'account_id', 'type_id', 'amount', 'currency', 'country'])
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

                $name = DataTableFormatter::showLink(
                    $data->enterprise,
                    'client.show',
                    $data->enterprise->name,
                    'view',
                    [$data->enterprise->id],
                    'fw-medium text-body',
                );
                $taxId = PaymentTableAmountFormatter::taxIdForPayment($data);

                if ($taxId === '')
                {
                    return $name;
                }

                return '<div>'.$name.'<br><small class="text-muted">'.e($taxId).'</small></div>';
            })
            ->filterColumn('enterprise_id', function ($query, $keyword)
            {
                $query->where(function ($inner) use ($keyword)
                {
                    $inner->whereHas('enterprise', function ($q) use ($keyword)
                    {
                        $q->whereRaw('name LIKE ?', ["%{$keyword}%"]);
                    })->orWhereHas('invoice.billingAddress', function ($q) use ($keyword)
                    {
                        $q->whereRaw('identification_number LIKE ?', ["%{$keyword}%"]);
                    });
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
                $date = $data->invoice?->date
                    ? Carbon::parse($data->invoice->date)
                    : Carbon::parse($data->date);

                return PaymentTableAmountFormatter::formatConverted(
                    (float) $data->amount,
                    $data->currency_code,
                    $this->reportingCurrency,
                    $date,
                    'text-danger fw-bold',
                );
            })
            ->addColumn('currency', function ($data)
            {
                return PaymentTableAmountFormatter::currencyBadge($data->currency_code);
            })
            ->addColumn('country', function ($data)
            {
                return PaymentTableAmountFormatter::countryBadge(
                    PaymentTableAmountFormatter::countryForPayment($data),
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
                'invoice.currency',
                'invoice.billingAddress',
                'invoice.stripeInvoiceSync',
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
            Column::make('amount')
                ->title(__('Amount'))
                ->addClass('min-tablet')
                ->className('text-end'),
            Column::computed('currency')
                ->title(__('Currency'))
                ->addClass('min-desktop')
                ->className('text-center')
                ->orderable(false)
                ->searchable(false),
            Column::computed('country')
                ->title(__('Country'))
                ->addClass('min-desktop')
                ->className('text-center')
                ->orderable(false)
                ->searchable(false),
            Column::make('status')
                ->title(__('Status'))
                ->addClass('min-phone')
                ->className('text-center'),
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
