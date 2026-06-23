<?php

namespace App\Http\Controllers;

use App\DataTables\ExpenseDataTable;
use App\Enums\TransactionType;
use App\Http\Requests\DetectExpenseDocumentRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceType;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Services\ExpenseDocumentDetectionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $enterprises = Enterprise::query()
            ->orderBy('name')
            ->get(['id', 'name', 'type_id']);

        $paymentAccounts = PaymentAccount::query()
            ->with('currency')
            ->orderBy('name')
            ->get();

        $paymentTypes = PaymentType::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $currencies = Currency::query()
            ->active()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol']);

        $documentTypes = [
            'invoice' => 'Factura',
            'receipt' => 'Ticket/Recibo',
            'tax' => 'Impuesto',
            'depreciation' => 'Amortización',
            'dividend' => 'Dividendo',
            'payroll' => 'Nómina',
            'loan' => 'Préstamo',
        ];

        $statusOptions = [
            1 => 'En proceso',
            2 => 'Aprobado',
            3 => 'Pendiente',
            4 => 'Rechazado',
        ];

        return view('expense.create', compact(
            'enterprises',
            'paymentAccounts',
            'paymentTypes',
            'currencies',
            'documentTypes',
            'statusOptions',
        ));
    }

    public function detectDocument(
        DetectExpenseDocumentRequest $request,
        ExpenseDocumentDetectionService $expenseDocumentDetectionService,
    ): JsonResponse {
        $teamId = (int) $request->user()->currentTeam->id;
        $detectedData = $expenseDocumentDetectionService->detectFromUploadedFile(
            $request->file('document_file'),
            $teamId,
        );

        return response()->json([
            'success' => true,
            'data' => $detectedData,
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $lineSummaries = $this->buildLineSummaries($validated['lines']);
        $teamId = (int) $request->user()->currentTeam->id;

        $status = ($validated['submit_action'] ?? 'save') === 'draft'
            ? 1
            : (int) $validated['status'];

        $linesTotal = (float) collect($lineSummaries)->sum('allocated_total');

        $amount = filled($validated['payment_amount'] ?? null)
            ? round((float) $validated['payment_amount'], 2)
            : round(max($linesTotal, 0.01), 2);

        $invoiceTotal = round(max($linesTotal, 0.01), 2);
        $invoiceBalance = round(max($invoiceTotal - $amount, 0), 2);
        $currencyCode = $this->resolveCurrencyCode($validated);
        $currencyId = $this->resolveCurrencyId($validated);
        $invoiceTypeId = $this->resolveInvoiceTypeId();
        $expenseCategoryId = isset($validated['expense_category_id'])
            ? (int) $validated['expense_category_id']
            : null;
        $expenseCategoryName = $this->resolveExpenseCategoryName($validated);

        DB::transaction(function () use (
            $validated,
            $teamId,
            $invoiceTypeId,
            $invoiceTotal,
            $invoiceBalance,
            $currencyId,
            $amount,
            $status,
            $currencyCode,
            $lineSummaries,
            $expenseCategoryId,
            $expenseCategoryName
        ): void {
            $invoice = Invoice::withoutGlobalScopes()->create([
                'team_id' => $teamId,
                'enterprise_id' => (int) $validated['enterprise_id'],
                'billing_id' => null,
                'type_id' => $invoiceTypeId,
                'operation' => 'buy',
                'number' => $this->composeInvoiceNumber($validated),
                'date' => $validated['date'],
                'due_date' => $validated['due_date'] ?? $validated['payment_date'],
                'gross_amount' => $invoiceTotal,
                'discount' => 0,
                'total_amount' => $invoiceTotal,
                'balance' => $invoiceBalance,
                'currency_id' => $currencyId,
                'status' => 2,
                'source_provider' => 'manual',
            ]);

            $this->createInvoiceItems($invoice, $lineSummaries, $expenseCategoryId);

            Payment::query()->create([
                'team_id' => $teamId,
                'enterprise_id' => (int) $validated['enterprise_id'],
                'transaction_type' => TransactionType::EXPENSE,
                'date' => $validated['date'],
                'invoice_id' => $invoice->id,
                'account_id' => (int) $validated['account_id'],
                'type_id' => (int) $validated['type_id'],
                'amount' => $amount,
                'remarks' => $this->buildRemarks($validated, $amount, $currencyCode, $lineSummaries, $expenseCategoryName),
                'status' => $status,
                'source_provider' => 'manual',
            ]);
        });

        $message = $status === 1
            ? 'Borrador de gasto guardado correctamente.'
            : 'Gasto guardado correctamente.';

        return redirect()->route('expense.index')->with('success', $message);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveExpenseCategoryName(array $validated): ?string
    {
        if (! empty($validated['expense_category_id']))
        {
            $categoryName = Category::query()
                ->whereKey((int) $validated['expense_category_id'])
                ->value('name');

            if (filled($categoryName))
            {
                return (string) $categoryName;
            }
        }

        if (filled($validated['expense_category'] ?? null))
        {
            return (string) $validated['expense_category'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveCurrencyId(array $validated): ?int
    {
        if (! empty($validated['currency_id']))
        {
            return (int) $validated['currency_id'];
        }

        $accountCurrencyId = PaymentAccount::query()
            ->whereKey((int) $validated['account_id'])
            ->value('currency_id');

        if ($accountCurrencyId !== null)
        {
            return (int) $accountCurrencyId;
        }

        return null;
    }

    private function resolveInvoiceTypeId(): int
    {
        return (int) (InvoiceType::query()->orderBy('id')->value('id') ?? 1);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function composeInvoiceNumber(array $validated): string
    {
        if (filled($validated['document_number'] ?? null))
        {
            return trim((string) $validated['document_number']);
        }

        return 'GC-'.Carbon::now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineSummaries
     */
    private function createInvoiceItems(Invoice $invoice, array $lineSummaries, ?int $categoryId): void
    {
        foreach ($lineSummaries as $lineSummary)
        {
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'category_id' => $categoryId,
                'description' => (string) $lineSummary['concept'],
                'quantity' => 1,
                'unit_price' => (float) $lineSummary['allocated_total'],
                'discount' => 0,
                'tax_percentage' => 0,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveCurrencyCode(array $validated): string
    {
        if (! empty($validated['currency_id']))
        {
            $selectedCurrencyCode = Currency::query()
                ->whereKey((int) $validated['currency_id'])
                ->value('code');

            if (filled($selectedCurrencyCode))
            {
                return strtoupper((string) $selectedCurrencyCode);
            }
        }

        $accountCurrencyCode = PaymentAccount::query()
            ->with('currency')
            ->whereKey((int) $validated['account_id'])
            ->first()
            ?->currency
            ?->code;

        if (filled($accountCurrencyCode))
        {
            return strtoupper((string) $accountCurrencyCode);
        }

        return 'EUR';
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function buildLineSummaries(array $lines): array
    {
        return collect($lines)->values()->map(function (array $line): array
        {
            $baseAmount = round((float) ($line['base_amount'] ?? 0), 2);
            $vatPercent = (float) ($line['vat_percent'] ?? 0);
            $retentionPercent = (float) ($line['retention_percent'] ?? 0);
            $allocationPercent = (float) ($line['allocation_percent'] ?? 100);

            $vatAmount = round($baseAmount * ($vatPercent / 100), 2);
            $retentionAmount = round($baseAmount * ($retentionPercent / 100), 2);
            $lineTotal = round($baseAmount + $vatAmount - $retentionAmount, 2);
            $allocatedTotal = round($lineTotal * ($allocationPercent / 100), 2);

            return [
                'concept' => (string) ($line['concept'] ?? ''),
                'base_amount' => $baseAmount,
                'vat_percent' => $vatPercent,
                'retention_percent' => $retentionPercent,
                'allocation_percent' => $allocationPercent,
                'vat_amount' => $vatAmount,
                'retention_amount' => $retentionAmount,
                'line_total' => $lineTotal,
                'allocated_total' => $allocatedTotal,
            ];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function buildRemarks(
        array $validated,
        float $amount,
        string $currencyCode,
        array $lineSummaries,
        ?string $expenseCategoryName,
    ): ?string {
        $lineRemarks = collect($lineSummaries)->map(function (array $line, int $index): string
        {
            return sprintf(
                'Línea %d: %s | Base: %.2f | IVA: %.2f%% | Retención: %.2f%% | Imputa: %.2f%% | Total: %.2f',
                $index + 1,
                (string) $line['concept'],
                (float) $line['base_amount'],
                (float) $line['vat_percent'],
                (float) $line['retention_percent'],
                (float) $line['allocation_percent'],
                (float) $line['allocated_total'],
            );
        })->implode(' || ');

        $remarks = array_filter([
            'Tipo de documento: '.(string) $validated['document_type'],
            filled($validated['document_number'] ?? null)
                ? 'Número de documento: '.(string) $validated['document_number']
                : null,
            filled($validated['due_date'] ?? null)
                ? 'Fecha de vencimiento: '.(string) $validated['due_date']
                : null,
            filled($expenseCategoryName)
                ? 'Tipo de gasto: '.(string) $expenseCategoryName
                : null,
            $lineRemarks,
            'Fecha de pago: '.(string) $validated['payment_date'],
            'Moneda: '.$currencyCode,
            'Total final: '.number_format($amount, 2, '.', '').' '.$currencyCode,
            ! empty($validated['cash_criteria']) ? 'Criterio de caja: sí' : null,
            ! empty($validated['is_investment']) ? 'Inversión: sí' : null,
            filled($validated['tags'] ?? null)
                ? 'Etiquetas: '.(string) $validated['tags']
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
