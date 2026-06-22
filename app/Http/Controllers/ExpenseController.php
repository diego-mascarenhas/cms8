<?php

namespace App\Http\Controllers;

use App\DataTables\ExpenseDataTable;
use App\Enums\TransactionType;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Enterprise;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(ExpenseDataTable $dataTable)
    {
        // Get accounts with balances
        $accounts = PaymentAccount::with('currency')
            ->get()
            ->map(function ($account)
            {
                $balance = Payment::where('account_id', $account->id)
                    ->where('status', 2) // Approved
                    ->selectRaw('COALESCE(SUM(CASE WHEN transaction_type = ? THEN amount ELSE -amount END), 0) as balance', [TransactionType::INCOME->value])
                    ->value('balance');

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'balance' => $balance,
                    'currency_code' => $account->currency->code ?? 'USD',
                ];
            });

        // Current month expenses
        $currentMonthExpense = Payment::where('transaction_type', TransactionType::EXPENSE)
            ->where('status', 2)
            ->whereMonth('date', Carbon::now()->month)
            ->whereYear('date', Carbon::now()->year)
            ->sum('amount');

        // Last month expenses
        $lastMonthExpense = Payment::where('transaction_type', TransactionType::EXPENSE)
            ->where('status', 2)
            ->whereMonth('date', Carbon::now()->subMonth()->month)
            ->whereYear('date', Carbon::now()->subMonth()->year)
            ->sum('amount');

        // Calculate percentage change
        $percentageChange = $lastMonthExpense > 0
            ? (($currentMonthExpense - $lastMonthExpense) / $lastMonthExpense) * 100
            : 0;

        // Year to date expenses
        $ytdExpense = Payment::where('transaction_type', TransactionType::EXPENSE)
            ->where('status', 2)
            ->whereYear('date', Carbon::now()->year)
            ->sum('amount');

        // Total approved expenses
        $totalExpense = Payment::where('transaction_type', TransactionType::EXPENSE)
            ->where('status', 2)
            ->sum('amount');

        return $dataTable->render('expense.index', compact(
            'accounts',
            'currentMonthExpense',
            'percentageChange',
            'ytdExpense',
            'totalExpense',
        ));
    }

    public function create(): View
    {
        $suppliers = Enterprise::query()
            ->suppliers()
            ->orderBy('name')
            ->get(['id', 'name']);

        $paymentAccounts = PaymentAccount::query()
            ->with('currency')
            ->orderBy('name')
            ->get();

        $paymentTypes = PaymentType::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $documentTypes = [
            'invoice' => __('Invoice'),
            'receipt' => __('Ticket / Receipt'),
            'tax' => __('Tax'),
            'depreciation' => __('Depreciation'),
            'dividend' => __('Dividend'),
            'payroll' => __('Payroll'),
            'loan' => __('Loan'),
        ];

        $statusOptions = [
            1 => __('In Process'),
            2 => __('Approved'),
            3 => __('Pending'),
            4 => __('Rejected'),
        ];

        return view('expense.create', compact(
            'suppliers',
            'paymentAccounts',
            'paymentTypes',
            'documentTypes',
            'statusOptions',
        ));
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $status = ($validated['submit_action'] ?? 'save') === 'draft'
            ? 1
            : (int) $validated['status'];

        $amount = $this->resolveFinalAmount($validated);

        Payment::query()->create([
            'team_id' => (int) $request->user()->currentTeam->id,
            'enterprise_id' => $validated['enterprise_id'] ?? null,
            'transaction_type' => TransactionType::EXPENSE,
            'date' => $validated['date'],
            'invoice_id' => null,
            'account_id' => (int) $validated['account_id'],
            'type_id' => (int) $validated['type_id'],
            'amount' => $amount,
            'remarks' => $this->buildRemarks($validated, $amount),
            'status' => $status,
            'source_provider' => 'manual',
        ]);

        $message = $status === 1
            ? __('Expense draft saved successfully.')
            : __('Expense created successfully.');

        return redirect()->route('expense.index')->with('success', $message);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveFinalAmount(array $validated): float
    {
        $baseAmount = (float) $validated['base_amount'];
        $vatPercent = (float) ($validated['vat_percent'] ?? 0);
        $retentionPercent = (float) ($validated['retention_percent'] ?? 0);
        $allocationPercent = (float) ($validated['allocation_percent'] ?? 100);

        $vatAmount = round($baseAmount * ($vatPercent / 100), 2);
        $retentionAmount = round($baseAmount * ($retentionPercent / 100), 2);
        $lineTotal = $baseAmount + $vatAmount - $retentionAmount;
        $allocatedTotal = round($lineTotal * ($allocationPercent / 100), 2);

        if (filled($validated['payment_amount'] ?? null))
        {
            return round((float) $validated['payment_amount'], 2);
        }

        return round(max($allocatedTotal, 0.01), 2);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function buildRemarks(array $validated, float $amount): ?string
    {
        $remarks = array_filter([
            'Document type: '.(string) $validated['document_type'],
            filled($validated['document_number'] ?? null)
                ? 'Document number: '.(string) $validated['document_number']
                : null,
            filled($validated['expense_category'] ?? null)
                ? 'Expense category: '.(string) $validated['expense_category']
                : null,
            'Concept: '.(string) $validated['concept'],
            'Base: '.number_format((float) $validated['base_amount'], 2, '.', ''),
            'VAT (%): '.number_format((float) ($validated['vat_percent'] ?? 0), 2, '.', ''),
            'Retention (%): '.number_format((float) ($validated['retention_percent'] ?? 0), 2, '.', ''),
            'Allocation (%): '.number_format((float) ($validated['allocation_percent'] ?? 100), 2, '.', ''),
            'Payment date: '.(string) $validated['payment_date'],
            'Final amount: '.number_format($amount, 2, '.', ''),
            ! empty($validated['cash_criteria']) ? 'Cash criteria: yes' : null,
            ! empty($validated['is_investment']) ? 'Investment: yes' : null,
            filled($validated['tags'] ?? null)
                ? 'Tags: '.(string) $validated['tags']
                : null,
            filled($validated['remarks'] ?? null)
                ? trim((string) $validated['remarks'])
                : null,
        ]);

        if ($remarks === [])
        {
            return null;
        }

        return implode(' | ', $remarks);
    }
}
