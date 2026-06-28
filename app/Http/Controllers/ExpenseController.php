<?php

namespace App\Http\Controllers;

use App\DataTables\ExpenseDataTable;
use App\Enums\TransactionType;
use App\Http\Requests\CheckExpenseDocumentDuplicateRequest;
use App\Http\Requests\DetectExpenseDocumentRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\StoreExpenseSupplierRequest;
use App\Models\Category;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceType;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\Team;
use App\Services\ExpenseDocumentDetectionService;
use App\Services\ExpenseDuplicateDocumentService;
use App\Services\ExpenseSupplierService;
use App\Services\Finance\PaymentAccountCompatibilityService;
use App\Support\ExpenseDocumentTypes;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly PaymentAccountCompatibilityService $paymentAccountCompatibilityService,
    ) {}

    public function index(ExpenseDataTable $dataTable)
    {
        $this->authorize('viewAny', Payment::class);

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
        $this->authorize('create', Payment::class);

        $teamId = (int) auth()->user()->currentTeam->id;

        $enterprises = Enterprise::query()
            ->orderBy('name')
            ->get(['id', 'name', 'type_id']);

        $paymentAccounts = PaymentAccount::withoutGlobalScopes()
            ->with(['currency', 'paymentTypes'])
            ->where('team_id', $teamId)
            ->where('status', 1)
            ->orderBy('name')
            ->get();

        $paymentTypesQuery = PaymentType::query()->orderBy('name');
        if (Schema::hasColumn('payment_types', 'is_active'))
        {
            $paymentTypesQuery->where('is_active', true);
        }
        $paymentTypes = $paymentTypesQuery->get(['id', 'name']);

        $preferredTypeId = $paymentTypes->firstWhere('id', 2)?->id
            ?? $paymentTypes->first()?->id;
        [$defaultPaymentAccountId, $defaultPaymentTypeId] = $this->paymentAccountCompatibilityService->resolveDefaults(
            $paymentAccounts,
            $paymentTypes,
            $preferredTypeId,
        );

        $paymentAccountOptions = $this->paymentAccountCompatibilityService->mapAccountsForFrontend($paymentAccounts);

        $currencies = Currency::query()
            ->active()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol']);

        $countries = Country::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $documentTypes = ExpenseDocumentTypes::LABELS;
        $disabledDocumentTypes = ExpenseDocumentTypes::DISABLED;

        $statusOptions = [
            1 => 'En proceso',
            2 => 'Aprobado',
            3 => 'Pendiente',
            4 => 'Rechazado',
        ];

        return view('expense.create', compact(
            'enterprises',
            'paymentAccounts',
            'paymentAccountOptions',
            'paymentTypes',
            'defaultPaymentTypeId',
            'defaultPaymentAccountId',
            'currencies',
            'countries',
            'documentTypes',
            'disabledDocumentTypes',
            'statusOptions',
        ));
    }

    public function detectDocument(
        DetectExpenseDocumentRequest $request,
        ExpenseDocumentDetectionService $expenseDocumentDetectionService,
    ): JsonResponse {
        $this->authorize('create', Payment::class);

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

    public function checkDocumentDuplicate(
        CheckExpenseDocumentDuplicateRequest $request,
        ExpenseDuplicateDocumentService $duplicateDocumentService,
    ): JsonResponse {
        $this->authorize('create', Payment::class);

        $teamId = (int) $request->user()->currentTeam->id;
        $validated = $request->validated();
        $duplicateInvoice = $duplicateDocumentService->findDuplicate(
            $teamId,
            (int) $validated['enterprise_id'],
            (string) $validated['document_number'],
        );

        if (! $duplicateInvoice instanceof Invoice)
        {
            return response()->json([
                'duplicate' => false,
            ]);
        }

        return response()->json([
            'duplicate' => true,
            'invoice' => [
                'id' => $duplicateInvoice->id,
                'number' => $duplicateInvoice->number,
                'date' => filled($duplicateInvoice->date)
                    ? Carbon::parse($duplicateInvoice->date)->format('Y-m-d')
                    : null,
                'total_amount' => number_format((float) $duplicateInvoice->total_amount, 2, '.', ''),
            ],
            'message' => 'Este número de comprobante ya fue registrado para este proveedor.',
        ]);
    }

    public function createSupplier(
        StoreExpenseSupplierRequest $request,
        ExpenseSupplierService $expenseSupplierService,
    ): JsonResponse {
        $this->authorize('create', Enterprise::class);

        $teamId = (int) $request->user()->currentTeam->id;
        $enterprise = $expenseSupplierService->createSupplier($teamId, $request->validated());

        return response()->json([
            'success' => true,
            'enterprise' => [
                'id' => $enterprise->id,
                'name' => $enterprise->name,
                'type_id' => $enterprise->type_id,
            ],
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $this->authorize('create', Payment::class);

        $validated = $request->validated();
        $lineSummaries = $this->buildLineSummaries($validated['lines']);
        $teamId = (int) $request->user()->currentTeam->id;
        $documentFile = $request->file('document_file');

        $invoiceTotal = round(max((float) collect($lineSummaries)->sum('allocated_total'), 0.01), 2);
        $paymentEntries = $this->resolvePaymentEntries($validated['payments'] ?? [], $invoiceTotal);
        $paymentsTotal = round((float) collect($paymentEntries)->sum('amount'), 2);
        $invoiceBalance = round(max($invoiceTotal - $paymentsTotal, 0), 2);
        $currencyCode = $this->resolveCurrencyCode($validated);
        $currencyId = $this->resolveCurrencyId($validated);
        $invoiceTypeId = $this->resolveInvoiceTypeId();
        $expenseCategoryId = isset($validated['expense_category_id'])
            ? (int) $validated['expense_category_id']
            : null;
        $expenseCategoryName = $this->resolveExpenseCategoryName($validated);
        $isDraft = ($validated['submit_action'] ?? 'save') === 'draft';

        DB::transaction(function () use (
            $validated,
            $teamId,
            $invoiceTypeId,
            $invoiceTotal,
            $invoiceBalance,
            $currencyId,
            $currencyCode,
            $lineSummaries,
            $expenseCategoryId,
            $expenseCategoryName,
            $documentFile,
            $paymentEntries,
            $isDraft
        ): void {
            $invoice = Invoice::withoutGlobalScopes()->create([
                'team_id' => $teamId,
                'enterprise_id' => (int) $validated['enterprise_id'],
                'billing_id' => null,
                'type_id' => $invoiceTypeId,
                'operation' => 'buy',
                'number' => $this->composeInvoiceNumber($validated),
                'date' => $validated['date'],
                'due_date' => $validated['due_date'] ?? collect($paymentEntries)->pluck('payment_date')->filter()->last() ?? $validated['date'],
                'gross_amount' => $invoiceTotal,
                'discount' => 0,
                'total_amount' => $invoiceTotal,
                'balance' => $invoiceBalance,
                'currency_id' => $currencyId,
                'status' => 2,
                'source_provider' => 'manual',
            ]);

            $storedDocumentPath = $this->storeExpenseDocumentFile(
                $documentFile,
                $teamId,
                (int) $invoice->id,
            );

            $this->createInvoiceItems($invoice, $lineSummaries, $expenseCategoryId);

            foreach ($paymentEntries as $paymentEntry)
            {
                Payment::query()->create([
                    'team_id' => $teamId,
                    'enterprise_id' => (int) $validated['enterprise_id'],
                    'transaction_type' => TransactionType::EXPENSE,
                    'date' => $paymentEntry['payment_date'],
                    'invoice_id' => $invoice->id,
                    'account_id' => (int) $paymentEntry['account_id'],
                    'type_id' => (int) $paymentEntry['type_id'],
                    'amount' => (float) $paymentEntry['amount'],
                    'remarks' => $this->buildRemarks(
                        $validated,
                        (float) $paymentEntry['amount'],
                        $currencyCode,
                        $lineSummaries,
                        $expenseCategoryName,
                        $storedDocumentPath,
                        $paymentEntries,
                    ),
                    'status' => $isDraft ? 1 : (int) $paymentEntry['status'],
                    'source_provider' => 'manual',
                ]);
            }
        });

        $message = $isDraft
            ? 'Borrador de gasto guardado correctamente.'
            : 'Gasto guardado correctamente.';

        return redirect()->route('expense.index')->with('success', $message);
    }

    /**
     * Store uploaded expense document using team hash path.
     */
    private function storeExpenseDocumentFile(?UploadedFile $documentFile, int $teamId, int $invoiceId): ?string
    {
        if (! $documentFile instanceof UploadedFile)
        {
            return null;
        }

        $teamHash = Team::generateTeamHash($teamId);
        $invoiceHash = substr(md5('invoice_salt_'.$invoiceId.'_'.config('app.key')), 0, 8);

        $originalName = pathinfo((string) $documentFile->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower((string) $documentFile->getClientOriginalExtension());
        $normalizedName = Str::slug(Str::ascii($originalName));

        if ($normalizedName === '')
        {
            $normalizedName = 'documento';
        }

        if ($extension === '')
        {
            $extension = 'pdf';
        }

        $fileName = $normalizedName.'-'.now()->format('YmdHis').'.'.$extension;
        $directory = "expenses/{$teamHash}/{$invoiceHash}";

        return $documentFile->storeAs($directory, $fileName, 'public');
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
    private function resolvePaymentAccountId(array $validated): ?int
    {
        $payments = $validated['payments'] ?? [];

        if (! is_array($payments))
        {
            return null;
        }

        foreach ($payments as $payment)
        {
            if (is_array($payment) && filled($payment['account_id'] ?? null))
            {
                return (int) $payment['account_id'];
            }
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

        $accountId = $this->resolvePaymentAccountId($validated);
        if ($accountId === null)
        {
            return null;
        }

        $accountCurrencyId = PaymentAccount::query()
            ->whereKey($accountId)
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
            $allocationFactor = (float) ($lineSummary['allocation_percent'] ?? 100) / 100;
            $unitPrice = round((float) ($lineSummary['base_amount'] ?? 0) * $allocationFactor, 2);
            $vatPercent = round((float) ($lineSummary['vat_percent'] ?? 0), 2);

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'category_id' => $categoryId,
                'description' => (string) $lineSummary['concept'],
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'discount' => 0,
                'tax_percentage' => $vatPercent,
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

        $accountId = $this->resolvePaymentAccountId($validated);
        if ($accountId === null)
        {
            return 'EUR';
        }

        $accountCurrencyCode = PaymentAccount::query()
            ->with('currency')
            ->whereKey($accountId)
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
     * @param  array<int, array<string, mixed>>  $payments
     * @return array<int, array<string, mixed>>
     */
    private function resolvePaymentEntries(array $payments, float $invoiceTotal): array
    {
        $resolved = [];

        foreach ($payments as $payment)
        {
            if (! filled($payment['amount'] ?? null))
            {
                continue;
            }

            $resolved[] = array_merge($payment, [
                'amount' => round((float) $payment['amount'], 2),
            ]);
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, array<string, mixed>>  $paymentEntries
     */
    private function buildRemarks(
        array $validated,
        float $amount,
        string $currencyCode,
        array $lineSummaries,
        ?string $expenseCategoryName,
        ?string $storedDocumentPath = null,
        array $paymentEntries = [],
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
            filled($storedDocumentPath)
                ? 'Documento: '.asset('storage/'.ltrim((string) $storedDocumentPath, '/'))
                : null,
            $lineRemarks,
            $paymentEntries !== []
                ? 'Pagos: '.collect($paymentEntries)->map(function (array $payment): string
                {
                    return number_format((float) $payment['amount'], 2, '.', '').' ('.(string) $payment['payment_date'].')';
                })->implode(', ')
                : null,
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
