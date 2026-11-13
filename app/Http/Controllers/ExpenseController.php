<?php

namespace App\Http\Controllers;

use App\DataTables\ExpenseDataTable;
use App\Http\Requests\CheckExpenseDocumentDuplicateRequest;
use App\Http\Requests\DetectExpenseDocumentRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\StoreExpenseSupplierRequest;
use App\Http\Requests\SuggestExpenseCategoriesRequest;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Services\ExpenseDocumentDetectionService;
use App\Services\ExpenseDuplicateDocumentService;
use App\Services\ExpenseSupplierService;
use App\Services\Finance\ManualInvoiceDocumentService;
use App\Services\Finance\PaymentAccountCompatibilityService;
use App\Services\Finance\PaymentReportingCurrencyService;
use App\Services\Finance\ServiceCategoryOptionsService;
use App\Services\Finance\VatHaciendaCsvExportService;
use App\Services\Finance\VatReportingService;
use App\Support\ExpenseDocumentTypes;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly PaymentAccountCompatibilityService $paymentAccountCompatibilityService,
        private readonly PaymentReportingCurrencyService $paymentReportingCurrencyService,
        private readonly VatReportingService $vatReportingService,
        private readonly VatHaciendaCsvExportService $vatHaciendaCsvExportService,
    ) {}

    public function index(Request $request, ExpenseDataTable $dataTable)
    {
        $this->authorize('viewAny', Payment::class);

        $accounts = $this->paymentReportingCurrencyService->accountBalancesForDisplay();
        $reportingCurrency = $this->paymentReportingCurrencyService->reportingCurrencyForCurrentTeam();

        $vatSelection = $this->vatReportingService->resolveSelectedPeriod(
            year: $request->integer('vat_year') ?: null,
            period: $request->string('vat_period')->toString() ?: null,
        );

        $periodRange = $vatSelection['range'];
        $previousPeriodRange = $this->vatReportingService->previousComparableRange($vatSelection);

        $teamId = (int) auth()->user()->currentTeam->id;
        $periodBooks = $this->vatHaciendaCsvExportService->bookSummary(
            $teamId,
            $periodRange['from'],
            $periodRange['to'],
            $reportingCurrency,
        );
        $periodExpense = $periodBooks['purchases'];

        $previousPeriodBooks = $this->vatHaciendaCsvExportService->bookSummary(
            $teamId,
            $previousPeriodRange['from'],
            $previousPeriodRange['to'],
            $reportingCurrency,
        );
        $previousPeriodExpense = $previousPeriodBooks['purchases'];

        $percentageChange = $previousPeriodExpense > 0
            ? (($periodExpense - $previousPeriodExpense) / $previousPeriodExpense) * 100
            : 0;

        $yearFrom = Carbon::create($vatSelection['year'], 1, 1)->startOfDay();
        $yearTo = $vatSelection['year'] === (int) now()->year
            ? now()->endOfDay()
            : Carbon::create($vatSelection['year'], 12, 31)->endOfDay();

        $yearBooks = $this->vatHaciendaCsvExportService->bookSummary(
            $teamId,
            $yearFrom,
            $yearTo,
            $reportingCurrency,
        );
        $yearExpense = $yearBooks['purchases'];

        $previousYearFrom = $yearFrom->copy()->subYear();
        $previousYearTo = $yearTo->copy()->subYear();

        $previousYearBooks = $this->vatHaciendaCsvExportService->bookSummary(
            $teamId,
            $previousYearFrom,
            $previousYearTo,
            $reportingCurrency,
        );
        $previousYearExpense = $previousYearBooks['purchases'];

        $yearPercentageChange = $previousYearExpense > 0
            ? (($yearExpense - $previousYearExpense) / $previousYearExpense) * 100
            : 0;

        $selectedVat = $periodBooks['input_vat'];

        $previousYearPeriodFrom = $periodRange['from']->copy()->subYear();
        $previousYearPeriodTo = $periodRange['to']->copy()->subYear();
        $previousYearVat = $this->vatHaciendaCsvExportService->bookSummary(
            $teamId,
            $previousYearPeriodFrom,
            $previousYearPeriodTo,
            $reportingCurrency,
        )['input_vat'];
        $vatPercentageChange = $previousYearVat > 0
            ? (($selectedVat - $previousYearVat) / $previousYearVat) * 100
            : 0;

        $selectedVatLabel = $vatSelection['label'];
        $periodLabel = $vatSelection['label'];
        $vatYears = $vatSelection['years'];
        $vatYear = $vatSelection['year'];
        $vatPeriod = $vatSelection['period'];
        $vatMode = $vatSelection['mode'];

        return $dataTable->render('expense.index', compact(
            'accounts',
            'periodExpense',
            'previousPeriodExpense',
            'percentageChange',
            'yearExpense',
            'previousYearExpense',
            'yearPercentageChange',
            'reportingCurrency',
            'selectedVat',
            'previousYearVat',
            'vatPercentageChange',
            'selectedVatLabel',
            'periodLabel',
            'vatYears',
            'vatYear',
            'vatPeriod',
            'vatMode',
        ));
    }

    public function exportHacienda(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Payment::class);

        $vatSelection = $this->vatReportingService->resolveSelectedPeriod(
            year: $request->integer('vat_year') ?: null,
            period: $request->string('vat_period')->toString() ?: null,
        );

        return $this->vatHaciendaCsvExportService->download(
            operation: 'buy',
            from: $vatSelection['range']['from'],
            to: $vatSelection['range']['to'],
            periodLabel: $vatSelection['label'],
            documentScope: 'invoices',
        );
    }

    public function exportCreditNotes(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Payment::class);

        $vatSelection = $this->vatReportingService->resolveSelectedPeriod(
            year: $request->integer('vat_year') ?: null,
            period: $request->string('vat_period')->toString() ?: null,
        );

        return $this->vatHaciendaCsvExportService->download(
            operation: 'buy',
            from: $vatSelection['range']['from'],
            to: $vatSelection['range']['to'],
            periodLabel: $vatSelection['label'],
            documentScope: 'credit_notes',
        );
    }

    public function create(): View
    {
        $this->authorize('create', Payment::class);

        return view('expense.create', $this->creationFormData());
    }

    public function movements(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $teamId = (int) $request->user()->currentTeam->id;
        $operation = strtolower(trim((string) $request->query('operation', 'all')));
        $search = trim((string) $request->query('search', ''));
        $vatSelection = $this->vatReportingService->resolveSelectedPeriod(
            year: $request->integer('vat_year') ?: ($request->integer('year') ?: null),
            period: $request->string('vat_period')->toString() ?: null,
            teamId: $teamId,
        );
        $year = (int) $vatSelection['year'];
        $period = (string) $vatSelection['period'];
        $periodLabel = (string) $vatSelection['label'];
        $years = array_values(array_map('intval', $vatSelection['years']));
        $rangeFrom = $vatSelection['range']['from']->toDateString();
        $rangeTo = $vatSelection['range']['to']->toDateString();
        $categoryId = $request->filled('category_id') ? $request->integer('category_id') : null;
        $uncategorized = $request->boolean('uncategorized');
        $perPage = min(max($request->integer('per_page') ?: 25, 1), 100);
        $categories = app(ServiceCategoryOptionsService::class)->optionsForInvoiceLines($teamId);
        $reportingCurrency = $this->paymentReportingCurrencyService->reportingCurrencyForTeam($teamId);

        $query = InvoiceItem::query()
            ->select('invoice_items.*')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.team_id', $teamId)
            ->whereNull('invoices.deleted_at')
            ->whereIn('invoices.operation', ['buy', 'sell'])
            ->whereDate('invoices.date', '>=', $rangeFrom)
            ->whereDate('invoices.date', '<=', $rangeTo)
            ->with([
                'category:id,name',
                'invoice:id,number,date,operation,enterprise_id,currency_id,source_reference_id',
                'invoice.enterprise:id,name',
                'invoice.currency:id,code',
                'invoice.stripeInvoiceSync',
            ]);

        if (in_array($operation, ['buy', 'sell'], true))
        {
            $query->where('invoices.operation', $operation);
        }

        if ($uncategorized)
        {
            $query->whereNull('invoice_items.category_id');
        } elseif ($categoryId)
        {
            $query->where('invoice_items.category_id', $categoryId);
        }

        if ($search !== '')
        {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void
            {
                $builder
                    ->where('invoice_items.description', 'like', $like)
                    ->orWhere('invoices.number', 'like', $like)
                    ->orWhereHas('invoice.enterprise', function ($enterpriseQuery) use ($like): void
                    {
                        $enterpriseQuery->where('name', 'like', $like);
                    })
                    ->orWhereHas('category', function ($categoryQuery) use ($like): void
                    {
                        $categoryQuery->where('name', 'like', $like);
                    });
            });
        }

        $paginator = $query
            ->orderByDesc('invoices.date')
            ->orderByDesc('invoice_items.id')
            ->paginate($perPage)
            ->withQueryString();

        $rows = collect($paginator->items())->map(function (InvoiceItem $item) use ($reportingCurrency): array
        {
            $invoice = $item->invoice;
            $operationValue = (string) ($invoice?->operation ?? 'buy');
            $isIncome = $operationValue === 'sell';
            $amount = round((float) $item->total, 2);
            $currencyCode = strtoupper((string) ($invoice?->currency_code ?: 'EUR'));
            $reportingAmount = $this->convertMovementAmount(
                $amount,
                $currencyCode,
                $reportingCurrency,
                $invoice?->date,
            );

            return [
                'id' => (int) $item->id,
                'date' => filled($invoice?->date)
                    ? Carbon::parse($invoice->date)->format('Y-m-d')
                    : null,
                'operation' => $operationValue,
                'operation_label' => $isIncome ? 'Ingreso' : 'Gasto',
                'enterprise_name' => $invoice?->enterprise?->name,
                'invoice_id' => $invoice?->id,
                'invoice_number' => $invoice?->number,
                // Sell → Stripe hosted invoice; buy uses Axis /expense/edit/{id}.
                'invoice_url' => $isIncome ? $invoice?->stripeHostedInvoiceUrl() : null,
                'description' => $item->description,
                'category_id' => $item->category_id ? (int) $item->category_id : null,
                'category_name' => $item->category?->name,
                'amount' => $amount,
                'signed_amount' => $isIncome ? $amount : -$amount,
                'currency_code' => $currencyCode,
                'reporting_amount' => $reportingAmount,
                'reporting_currency' => $reportingCurrency,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'year' => $year,
            'vat_year' => $year,
            'vat_period' => $period,
            'period_label' => $periodLabel,
            'years' => $years,
            'operation' => in_array($operation, ['buy', 'sell'], true) ? $operation : 'all',
            'category_id' => $uncategorized ? 0 : $categoryId,
            'categories' => $categories,
            'reporting_currency' => $reportingCurrency,
            'data' => [
                'data' => $rows,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function convertMovementAmount(
        float $amount,
        string $fromCurrency,
        string $reportingCurrency,
        mixed $invoiceDate,
    ): ?float {
        $fromCurrency = strtoupper(trim($fromCurrency));
        $reportingCurrency = strtoupper(trim($reportingCurrency));

        if ($fromCurrency === $reportingCurrency)
        {
            return round($amount, 2);
        }

        if (blank($invoiceDate))
        {
            return ExchangeRate::convert($amount, $fromCurrency, $reportingCurrency);
        }

        $conversionDate = Carbon::parse($invoiceDate)->endOfMonth();
        $converted = ExchangeRate::convertOnOrBeforeDate($amount, $fromCurrency, $reportingCurrency, $conversionDate);

        if ($converted !== null)
        {
            return $converted;
        }

        return ExchangeRate::convert($amount, $fromCurrency, $reportingCurrency);
    }

    public function form(): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $data = $this->creationFormData();

        return response()->json([
            'success' => true,
            'can_create_supplier' => auth()->user()->can('create', Enterprise::class),
            'document_types' => collect($data['documentTypes'])->map(function (string $label, string $key) use ($data): array
            {
                return [
                    'key' => $key,
                    'label' => $label,
                    'disabled' => in_array($key, $data['disabledDocumentTypes'], true),
                ];
            })->values(),
            'enterprises' => $data['enterprises']->map(function (Enterprise $enterprise): array
            {
                return [
                    'id' => (int) $enterprise->id,
                    'name' => (string) $enterprise->name,
                ];
            })->values(),
            'payment_accounts' => $data['paymentAccountOptions'],
            'payment_types' => $data['paymentTypes']->map(function (PaymentType $type): array
            {
                return [
                    'id' => (int) $type->id,
                    'name' => (string) ($type->display_name ?? $type->name),
                ];
            })->values(),
            'default_payment_type_id' => $data['defaultPaymentTypeId'],
            'default_payment_account_id' => $data['defaultPaymentAccountId'],
            'currencies' => $data['currencies']->map(function (Currency $currency): array
            {
                return [
                    'id' => (int) $currency->id,
                    'code' => (string) $currency->code,
                    'name' => (string) $currency->name,
                    'symbol' => (string) $currency->symbol,
                ];
            })->values(),
            'countries' => $data['countries']->map(function (Country $country): array
            {
                return [
                    'id' => (int) $country->id,
                    'code' => (string) $country->code,
                    'name' => (string) $country->name,
                ];
            })->values(),
            'statuses' => collect($data['statusOptions'])->map(function (string $label, int $id): array
            {
                return [
                    'id' => $id,
                    'label' => $label,
                ];
            })->values(),
            'categories' => $data['expenseCategoryOptions'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function creationFormData(): array
    {
        $teamId = (int) auth()->user()->currentTeam->id;

        $enterprises = Enterprise::query()
            ->with([
                'type:id,name',
                'responsible:id,name,email',
                'contacts' => function ($query): void
                {
                    $query->select('contacts.id', 'contacts.name', 'contacts.surname', 'contacts.email');
                },
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'type_id', 'responsible_id']);

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

        $expenseCategoryOptions = app(ServiceCategoryOptionsService::class)->optionsForInvoiceLines($teamId);
        $documentFlow = [
            'mode' => 'buy',
            'page_title' => 'Añadir gasto',
            'breadcrumb' => 'Gastos',
            'subtitle' => 'Registrar un nuevo gasto',
            'back_route' => route('expense.index'),
            'store_route' => route('expense.store'),
            'party_label' => 'Proveedor (*)',
            'party_placeholder' => 'Selecciona un proveedor',
            'create_party_label' => 'Crear proveedor',
            'create_party_modal_title' => 'Crear proveedor',
            'create_party_help' => 'Los datos fiscales se guardarán en el proveedor para futuras facturas.',
            'save_party_label' => 'Guardar proveedor',
            'remarks_label' => 'Comentario personal del gasto',
            'submit_label' => 'Guardar gasto',
            'account_hint' => 'antes de registrar el gasto.',
            'payments_overflow_suffix' => 'total del gasto.',
            'duplicate_message' => 'Este número de comprobante ya fue registrado para este proveedor.',
            'detect_document_url' => route('expense.detect-document'),
            'check_duplicate_url' => route('expense.check-document-duplicate'),
            'create_party_url' => route('expense.create-supplier'),
            'suggested_categories_url' => route('expense.suggested-categories'),
            'livewire_key' => 'expense-line-cat-mgr-services',
        ];

        return compact(
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
            'expenseCategoryOptions',
            'documentFlow',
        );
    }

    public function detectDocument(
        DetectExpenseDocumentRequest $request,
        ExpenseDocumentDetectionService $expenseDocumentDetectionService,
        ManualInvoiceDocumentService $manualInvoiceDocumentService,
    ): JsonResponse {
        $this->authorize('create', Payment::class);

        $teamId = (int) $request->user()->currentTeam->id;
        $documentFile = $request->file('document_file');
        $pendingDocumentToken = $documentFile instanceof UploadedFile
            ? $manualInvoiceDocumentService->storePendingDocument($documentFile, $teamId)
            : null;
        $detectedData = $expenseDocumentDetectionService->detectFromUploadedFile(
            $documentFile,
            $teamId,
        );

        return response()->json([
            'success' => true,
            'data' => $detectedData,
            'pending_document_token' => $pendingDocumentToken,
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

    public function suggestedCategories(SuggestExpenseCategoriesRequest $request): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $teamId = (int) $request->user()->currentTeam->id;
        $enterpriseId = (int) $request->validated('enterprise_id');

        $previousInvoice = Invoice::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('enterprise_id', $enterpriseId)
            ->where('operation', 'buy')
            ->whereHas('items', fn ($query) => $query->whereNotNull('category_id'))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first(['id', 'number', 'date']);

        if (! $previousInvoice instanceof Invoice)
        {
            return response()->json([
                'success' => true,
                'invoice' => null,
                'items' => [],
            ]);
        }

        $items = InvoiceItem::query()
            ->with('category:id,name')
            ->where('invoice_id', $previousInvoice->id)
            ->orderBy('id')
            ->get(['id', 'invoice_id', 'category_id', 'description'])
            ->map(function (InvoiceItem $item): array
            {
                return [
                    'category_id' => $item->category_id ? (int) $item->category_id : null,
                    'category_name' => $item->category?->name,
                    'description' => (string) $item->description,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'invoice' => [
                'id' => $previousInvoice->id,
                'number' => $previousInvoice->number,
                'date' => filled($previousInvoice->date)
                    ? Carbon::parse($previousInvoice->date)->format('Y-m-d')
                    : null,
            ],
            'items' => $items,
        ]);
    }

    public function store(
        StoreExpenseRequest $request,
        ManualInvoiceDocumentService $manualInvoiceDocumentService,
    ): JsonResponse|RedirectResponse {
        $this->authorize('create', Payment::class);

        $teamId = (int) $request->user()->currentTeam->id;
        $validated = $request->validated();
        $result = $manualInvoiceDocumentService->store(
            $validated,
            $teamId,
            $request->file('document_file'),
            'buy',
            isset($validated['pending_document_token'])
                ? (string) $validated['pending_document_token']
                : null,
        );

        $message = $result['is_draft']
            ? 'Borrador de gasto guardado correctamente.'
            : (($validated['document_type'] ?? '') === 'credit_note'
                ? 'Nota de crédito guardada correctamente.'
                : 'Gasto guardado correctamente.');

        if ($request->expectsJson())
        {
            return response()->json([
                'success' => true,
                'message' => $message,
                'is_draft' => $result['is_draft'],
                'invoice_id' => $result['invoice']?->id,
            ]);
        }

        return redirect()->route('expense.index')->with('success', $message);
    }
}
