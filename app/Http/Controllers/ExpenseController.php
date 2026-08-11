<?php

namespace App\Http\Controllers;

use App\DataTables\ExpenseDataTable;
use App\Enums\TransactionType;
use App\Http\Requests\CheckExpenseDocumentDuplicateRequest;
use App\Http\Requests\DetectExpenseDocumentRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\StoreExpenseSupplierRequest;
use App\Http\Requests\SuggestExpenseCategoriesRequest;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Enterprise;
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

        $periodExpense = $this->paymentReportingCurrencyService->sumApprovedPaymentsConverted(
            TransactionType::EXPENSE,
            $reportingCurrency,
            fn ($query) => $query
                ->whereDate('payments.date', '>=', $periodRange['from']->toDateString())
                ->whereDate('payments.date', '<=', $periodRange['to']->toDateString()),
        );

        $previousPeriodExpense = $this->paymentReportingCurrencyService->sumApprovedPaymentsConverted(
            TransactionType::EXPENSE,
            $reportingCurrency,
            fn ($query) => $query
                ->whereDate('payments.date', '>=', $previousPeriodRange['from']->toDateString())
                ->whereDate('payments.date', '<=', $previousPeriodRange['to']->toDateString()),
        );

        $percentageChange = $previousPeriodExpense > 0
            ? (($periodExpense - $previousPeriodExpense) / $previousPeriodExpense) * 100
            : 0;

        $yearFrom = Carbon::create($vatSelection['year'], 1, 1)->startOfDay();
        $yearTo = $vatSelection['year'] === (int) now()->year
            ? now()->endOfDay()
            : Carbon::create($vatSelection['year'], 12, 31)->endOfDay();

        $yearExpense = $this->paymentReportingCurrencyService->sumApprovedPaymentsConverted(
            TransactionType::EXPENSE,
            $reportingCurrency,
            fn ($query) => $query
                ->whereDate('payments.date', '>=', $yearFrom->toDateString())
                ->whereDate('payments.date', '<=', $yearTo->toDateString()),
        );

        $previousYearFrom = $yearFrom->copy()->subYear();
        $previousYearTo = $yearTo->copy()->subYear();

        $previousYearExpense = $this->paymentReportingCurrencyService->sumApprovedPaymentsConverted(
            TransactionType::EXPENSE,
            $reportingCurrency,
            fn ($query) => $query
                ->whereDate('payments.date', '>=', $previousYearFrom->toDateString())
                ->whereDate('payments.date', '<=', $previousYearTo->toDateString()),
        );

        $yearPercentageChange = $previousYearExpense > 0
            ? (($yearExpense - $previousYearExpense) / $previousYearExpense) * 100
            : 0;

        $selectedVat = $this->vatReportingService->sumExpenseVat(
            $periodRange['from'],
            $periodRange['to'],
            $reportingCurrency,
        );

        $previousYearPeriodFrom = $periodRange['from']->copy()->subYear();
        $previousYearPeriodTo = $periodRange['to']->copy()->subYear();
        $previousYearVat = $this->vatReportingService->sumExpenseVat(
            $previousYearPeriodFrom,
            $previousYearPeriodTo,
            $reportingCurrency,
        );
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
            'expenseCategoryOptions',
            'documentFlow',
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
    ): RedirectResponse {
        $this->authorize('create', Payment::class);

        $teamId = (int) $request->user()->currentTeam->id;
        $result = $manualInvoiceDocumentService->store(
            $request->validated(),
            $teamId,
            $request->file('document_file'),
            'buy',
        );

        $message = $result['is_draft']
            ? 'Borrador de gasto guardado correctamente.'
            : 'Gasto guardado correctamente.';

        return redirect()->route('expense.index')->with('success', $message);
    }
}
