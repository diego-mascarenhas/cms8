<?php

namespace App\Http\Controllers;

use App\DataTables\InvoiceDataTable;
use App\Http\Requests\CheckExpenseDocumentDuplicateRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\StoreExpenseSupplierRequest;
use App\Http\Requests\StoreInvoiceCreditNoteRequest;
use App\Http\Requests\StoreInvoiceElectronicPaymentRequest;
use App\Http\Requests\StoreInvoicePaymentRequest;
use App\Http\Requests\SuggestExpenseCategoriesRequest;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\ExchangeRate;
use App\Models\FiscalExport;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Services\Billing\InvoiceInboundSyncService;
use App\Services\Billing\MercadoPagoPaymentImportService;
use App\Services\Billing\StripeInvoiceCreditNoteService;
use App\Services\ExpenseDuplicateDocumentService;
use App\Services\ExpenseSupplierService;
use App\Services\Finance\InvoiceCreditNoteService;
use App\Services\Finance\InvoiceDisplayLineItemService;
use App\Services\Finance\InvoiceElectronicPaymentLinkService;
use App\Services\Finance\InvoicePaymentDetailService;
use App\Services\Finance\InvoicePaymentRegistrationService;
use App\Services\Finance\InvoiceSummaryService;
use App\Services\Finance\ManualInvoiceDocumentService;
use App\Services\Finance\PaymentAccountCompatibilityService;
use App\Services\Finance\PaymentStatusUpdateService;
use App\Services\Finance\ServiceCategoryOptionsService;
use App\Services\Fiscal\Exceptions\FiscalExportException;
use App\Services\Fiscal\FiscalExportRouter;
use App\Services\Fiscal\FiscalExportService;
use App\Services\Fiscal\NullFiscalExportAdapter;
use App\Support\ExpenseDocumentTypes;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoicePaymentRegistrationService $invoicePaymentRegistrationService,
        private readonly InvoiceElectronicPaymentLinkService $invoiceElectronicPaymentLinkService,
        private readonly InvoiceCreditNoteService $invoiceCreditNoteService,
        private readonly StripeInvoiceCreditNoteService $stripeInvoiceCreditNoteService,
        private readonly MercadoPagoPaymentImportService $mercadoPagoPaymentImportService,
    ) {
        // Note: Manual authorization in each method due to non-standard route parameter names
        // Laravel's authorizeResource() expects {invoice} parameter, but routes use {id}
    }

    public function index(InvoiceDataTable $dataTable)
    {
        $this->authorize('viewAny', Invoice::class);

        // Obtener tipos de cambio actuales
        $exchangeRates = [
            'USD_ARS' => ExchangeRate::getLatestRate('USD', 'ARS'),
            'USD_EUR' => ExchangeRate::getLatestRate('USD', 'EUR'),
            'ARS_EUR' => ExchangeRate::getLatestRate('ARS', 'EUR'),
        ];

        // Obtener fecha de última actualización
        $lastUpdate = ExchangeRate::latest('fetched_at')->first();

        $invoiceStats = app(InvoiceSummaryService::class)->buildIndexStats(
            (int) auth()->user()->currentTeam->id,
        );

        $initialSummaryFilter = app(InvoiceSummaryService::class)->resolveListFilter(
            request()->query('summary_filter'),
        );

        $team = auth()->user()->currentTeam;
        $inboundSyncService = app(InvoiceInboundSyncService::class);
        $invoiceSyncProviders = $team ? $inboundSyncService->availableProviders($team) : [];

        return $dataTable->render('invoice.index', compact(
            'exchangeRates',
            'lastUpdate',
            'invoiceStats',
            'initialSummaryFilter',
            'invoiceSyncProviders',
        ));
    }

    public function create(): View
    {
        $this->authorize('create', Invoice::class);

        $teamId = (int) auth()->user()->currentTeam->id;
        $enterprises = Enterprise::query()
            ->where('type_id', 1)
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
        [$defaultPaymentAccountId, $defaultPaymentTypeId] = app(PaymentAccountCompatibilityService::class)
            ->resolveDefaults($paymentAccounts, $paymentTypes, $preferredTypeId);
        $paymentAccountOptions = app(PaymentAccountCompatibilityService::class)
            ->mapAccountsForFrontend($paymentAccounts);

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
            'mode' => 'sell',
            'page_title' => 'Crear factura',
            'breadcrumb' => 'Facturas',
            'subtitle' => 'Registrar una nueva factura',
            'back_route' => route('invoice.index'),
            'store_route' => route('invoice.store'),
            'party_label' => 'Cliente (*)',
            'party_placeholder' => 'Selecciona un cliente',
            'create_party_label' => 'Crear cliente',
            'create_party_modal_title' => 'Crear cliente',
            'create_party_help' => 'Los datos fiscales se guardarán en el cliente para futuras facturas.',
            'save_party_label' => 'Guardar cliente',
            'remarks_label' => 'Comentario personal de la factura',
            'submit_label' => 'Guardar factura',
            'account_hint' => 'antes de registrar la factura.',
            'payments_section_title' => 'Cobros',
            'add_payment_label' => 'Añadir cobro',
            'payment_date_label' => 'Fecha del cobro (*)',
            'remove_payment_title' => 'Eliminar cobro',
            'payments_empty_message' => 'Sin cobros registrados. La factura quedará pendiente de cobro. Usa «Añadir cobro» si quieres registrar uno.',
            'payments_empty_summary' => 'Sin cobros registrados. Pendiente de cobro:',
            'paid_label' => 'Cobrado',
            'payments_overflow_prefix' => 'La suma de cobros supera el',
            'payments_overflow_suffix' => 'total de la factura.',
            'duplicate_message' => 'Este número de comprobante ya fue registrado para este cliente.',
            'detect_document_url' => null,
            'check_duplicate_url' => route('invoice.check-document-duplicate'),
            'create_party_url' => route('invoice.create-client'),
            'suggested_categories_url' => route('invoice.suggested-categories'),
            'livewire_key' => 'invoice-line-cat-mgr-services',
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

    public function store(
        StoreExpenseRequest $request,
        ManualInvoiceDocumentService $manualInvoiceDocumentService,
    ): RedirectResponse {
        $this->authorize('create', Invoice::class);

        $result = $manualInvoiceDocumentService->store(
            $request->validated(),
            (int) $request->user()->currentTeam->id,
            $request->file('document_file'),
            'sell',
        );

        $message = $result['is_draft']
            ? 'Borrador de factura guardado correctamente.'
            : 'Factura guardada correctamente.';

        return redirect()->route('invoice.index')->with('success', $message);
    }

    public function createClient(
        StoreExpenseSupplierRequest $request,
        ExpenseSupplierService $expenseSupplierService,
    ): JsonResponse {
        $this->authorize('create', Enterprise::class);

        $enterprise = $expenseSupplierService->createClient(
            (int) $request->user()->currentTeam->id,
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'enterprise' => [
                'id' => $enterprise->id,
                'name' => $enterprise->name,
                'type_id' => $enterprise->type_id,
            ],
        ]);
    }

    public function checkDocumentDuplicate(
        CheckExpenseDocumentDuplicateRequest $request,
        ExpenseDuplicateDocumentService $duplicateDocumentService,
    ): JsonResponse {
        $this->authorize('create', Invoice::class);

        $validated = $request->validated();
        $duplicateInvoice = $duplicateDocumentService->findDuplicate(
            (int) $request->user()->currentTeam->id,
            (int) $validated['enterprise_id'],
            (string) $validated['document_number'],
            'sell',
        );

        if (! $duplicateInvoice instanceof Invoice)
        {
            return response()->json(['duplicate' => false]);
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
            'message' => 'Este número de comprobante ya fue registrado para este cliente.',
        ]);
    }

    public function suggestedCategories(SuggestExpenseCategoriesRequest $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $previousInvoice = Invoice::withoutGlobalScopes()
            ->where('team_id', (int) $request->user()->currentTeam->id)
            ->where('enterprise_id', (int) $request->validated('enterprise_id'))
            ->where('operation', 'sell')
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

    /**
     * Pull invoices from configured inbound providers (Stripe, Cuéntica) for the current team.
     */
    public function syncInbound(InvoiceInboundSyncService $inboundSyncService): RedirectResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('invoice.index')->with('error', __('invoice_sync.errors.no_team'));
        }

        if (! $inboundSyncService->canSync($team))
        {
            return redirect()->route('invoice.index')->with('error', __('invoice_sync.errors.nothing_configured'));
        }

        $result = $inboundSyncService->syncForTeam($team);

        if ($result['imported'] === 0 && $result['updated'] === 0 && $result['skipped'] > 0)
        {
            return redirect()->route('invoice.index')->with(
                'warning',
                __('invoice_sync.sync_warning_skipped', [
                    'providers' => $inboundSyncService->providerLabels($result['providers']),
                    'skipped' => $result['skipped'],
                ]),
            );
        }

        return redirect()->route('invoice.index')->with(
            'success',
            __('invoice_sync.sync_success', [
                'providers' => $inboundSyncService->providerLabels($result['providers']),
                'imported' => $result['imported'],
                'updated' => $result['updated'],
            ]),
        );
    }

    /**
     * Form: set enterprise (client) for an invoice that has no company yet.
     */
    public function linkEnterpriseForm(Invoice $invoice): View|RedirectResponse
    {
        $this->authorize('viewAny', Invoice::class);
        $this->denyIfCannotLinkEnterprise();

        if ($invoice->enterprise_id)
        {
            return redirect()->route('invoice.index')->with('error', __('invoice_enterprise.link.errors.already_linked'));
        }

        $enterprises = $this->enterprisesForLinking();

        return view('invoice.link-enterprise', [
            'invoice' => $invoice->loadMissing(['type']),
            'enterprises' => $enterprises,
        ]);
    }

    public function linkEnterprise(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('viewAny', Invoice::class);
        $this->denyIfCannotLinkEnterprise();

        if ($invoice->enterprise_id)
        {
            return redirect()->route('invoice.index')->with('error', __('invoice_enterprise.link.errors.already_linked'));
        }

        $teamId = (int) auth()->user()->currentTeam->id;
        $validated = $request->validate([
            'enterprise_id' => [
                'required',
                'integer',
                Rule::exists('enterprises', 'id')
                    ->where(fn ($q) => $q
                        ->where('team_id', $teamId)
                        ->where('type_id', 1)
                        ->whereNull('deleted_at')),
            ],
        ]);

        $enterprise = Enterprise::query()->findOrFail($validated['enterprise_id']);
        $this->authorize('update', $enterprise);
        $this->assertCollaboratorOwnsEnterprise($enterprise);

        $invoice->update([
            'enterprise_id' => $enterprise->id,
        ]);

        return redirect()->route('invoice.index')->with('success', __('invoice_enterprise.link.success'));
    }

    private function denyIfCannotLinkEnterprise(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasAnyRole(['admin', 'collaborator']))
        {
            abort(403);
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Enterprise>
     */
    private function enterprisesForLinking()
    {
        $user = auth()->user();
        $query = Enterprise::query()
            ->clients()
            ->orderBy('name');

        if ($user->hasRole('admin'))
        {
            return $query->get();
        }

        return $query->where('responsible_id', $user->id)->get();
    }

    private function assertCollaboratorOwnsEnterprise(Enterprise $enterprise): void
    {
        $user = auth()->user();
        if ($user->hasRole('admin'))
        {
            return;
        }

        if ((int) $enterprise->getAttribute('responsible_id') !== (int) $user->id)
        {
            abort(403);
        }
    }

    public function show($id): View
    {
        $invoice = Invoice::with([
            'enterprise.enterpriseBillingAddresses.taxStatusType',
            'billingAddress.taxStatusType',
            'items.category',
            'type',
            'stripeInvoiceSync',
            'fiscalExports',
        ])->findOrFail($id);

        $this->authorize('view', $invoice);

        $fiscalPlatform = app(FiscalExportRouter::class)->resolvePlatform($invoice);
        if ($fiscalPlatform === NullFiscalExportAdapter::PLATFORM)
        {
            $fiscalPlatform = null;
        }
        $fiscalExport = $fiscalPlatform
            ? $invoice->fiscalExports->firstWhere('platform', $fiscalPlatform)
            : null;
        $canExportFiscal = auth()->user()->can('invoice.edit')
            && app(FiscalExportService::class)->isEligible($invoice);

        $paymentDetails = app(InvoicePaymentDetailService::class)->forInvoice($invoice);
        $displayLineItems = app(InvoiceDisplayLineItemService::class)->forInvoice($invoice);
        $canLinkElectronicPayment = $this->invoiceElectronicPaymentLinkService->canLink(auth()->user(), $invoice);
        $electronicPaymentSyncOptions = $canLinkElectronicPayment
            ? $this->invoiceElectronicPaymentLinkService->syncOptions($invoice)
            : [];
        $canRegisterPayment = $this->invoicePaymentRegistrationService->canRegisterPayment(auth()->user(), $invoice);
        $paymentFormDefaults = $canRegisterPayment
            ? $this->invoicePaymentRegistrationService->formDefaults($invoice)
            : null;
        $canShowCreditNoteForm = $this->invoiceCreditNoteService->canShowCreditNoteForm(auth()->user(), $invoice);
        $canIssueCreditNote = $this->invoiceCreditNoteService->canIssueCreditNote(auth()->user(), $invoice);
        $creditNoteReasons = InvoiceCreditNoteService::STRIPE_REASONS;
        $defaultCreditNoteReason = $this->invoiceCreditNoteService->defaultReason();
        $canUpdatePaymentStatus = auth()->user()->currentTeam
            && (int) $invoice->team_id === (int) auth()->user()->currentTeam->id
            && auth()->user()->ownsTeam(auth()->user()->currentTeam);
        $canResyncPayment = $canUpdatePaymentStatus
            && Payment::withoutGlobalScopes()
                ->where('team_id', $invoice->team_id)
                ->where('invoice_id', $invoice->id)
                ->where('source_provider', 'mercadopago')
                ->exists();
        $paymentStatusOptions = app(PaymentStatusUpdateService::class)->selectableStatuses();
        $originalInvoice = $invoice->isCreditNote() ? $invoice->originalInvoice() : null;
        $existingCreditNote = (! $invoice->isCreditNote()) ? $invoice->existingCreditNote() : null;

        return view('invoices.show', compact(
            'invoice',
            'paymentDetails',
            'displayLineItems',
            'canLinkElectronicPayment',
            'electronicPaymentSyncOptions',
            'canRegisterPayment',
            'paymentFormDefaults',
            'canShowCreditNoteForm',
            'canIssueCreditNote',
            'creditNoteReasons',
            'defaultCreditNoteReason',
            'canUpdatePaymentStatus',
            'canResyncPayment',
            'paymentStatusOptions',
            'fiscalPlatform',
            'fiscalExport',
            'canExportFiscal',
            'originalInvoice',
            'existingCreditNote',
        ));
    }

    public function exportFiscal(Invoice $invoice, FiscalExportService $service): RedirectResponse
    {
        $this->authorize('view', $invoice);

        if (! auth()->user()->can('invoice.edit'))
        {
            abort(403);
        }

        try
        {
            $export = $service->export($invoice, force: request()->boolean('force'));
        } catch (FiscalExportException $exception)
        {
            return redirect()
                ->route('invoice.show', $invoice->id)
                ->with('error', __('Error al exportar la factura: :message', ['message' => $exception->getMessage()]));
        }

        if (! $export instanceof FiscalExport)
        {
            return redirect()
                ->route('invoice.show', $invoice->id)
                ->with('error', __('La exportación fiscal está deshabilitada.'));
        }

        return match ($export->status)
        {
            FiscalExport::STATUS_EXPORTED, FiscalExport::STATUS_RECTIFIED => redirect()
                ->route('invoice.show', $invoice->id)
                ->with('success', __('Factura exportada a :platform (Nº :number).', [
                    'platform' => ucfirst($export->platform),
                    'number' => $export->external_number ?: $export->external_id,
                ])),
            FiscalExport::STATUS_SKIPPED => redirect()
                ->route('invoice.show', $invoice->id)
                ->with('error', __('No se exportó: :message', ['message' => $export->error_message])),
            default => redirect()
                ->route('invoice.show', $invoice->id)
                ->with('error', __('Error al exportar la factura: :message', ['message' => $export->error_message])),
        };
    }

    public function storePayment(StoreInvoicePaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('view', $invoice);

        if (! $this->invoicePaymentRegistrationService->canRegisterPayment($request->user(), $invoice))
        {
            abort(403);
        }

        try
        {
            $this->invoicePaymentRegistrationService->register($request->user(), $invoice, $request->validated());
        } catch (ValidationException $exception)
        {
            return back()->withInput()->withErrors($exception->errors());
        }

        return redirect()
            ->route('invoice.show', $invoice->id)
            ->with('success', __('invoice_payment.success'));
    }

    public function storeElectronicPayment(StoreInvoiceElectronicPaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('view', $invoice);

        if (! $this->invoiceElectronicPaymentLinkService->canLink($request->user(), $invoice))
        {
            abort(403);
        }

        try
        {
            $this->invoiceElectronicPaymentLinkService->link(
                $request->user(),
                $invoice,
                $request->paymentSync(),
            );
        } catch (ValidationException $exception)
        {
            return back()->withInput()->withErrors($exception->errors());
        }

        return redirect()
            ->route('invoice.show', $invoice->id)
            ->with('success', __('invoice_payment.electronic_success'));
    }

    public function resyncPayment(Invoice $invoice): RedirectResponse
    {
        $this->authorize('view', $invoice);

        $user = auth()->user();
        if (! $user->currentTeam
            || (int) $invoice->team_id !== (int) $user->currentTeam->id
            || ! $user->ownsTeam($user->currentTeam))
        {
            abort(403);
        }

        $result = $this->mercadoPagoPaymentImportService->resyncInvoicePayments($invoice);

        if ($result['count'] === 0)
        {
            return redirect()
                ->route('invoice.show', $invoice->id)
                ->with('error', __('invoice_payment.resync_none'));
        }

        if ($result['stripe_updated'])
        {
            return redirect()
                ->route('invoice.show', $invoice->id)
                ->with('success', __('invoice_payment.resync_success'));
        }

        return redirect()
            ->route('invoice.show', $invoice->id)
            ->with('warning', __('invoice_payment.resync_local_only'));
    }

    public function storeCreditNote(StoreInvoiceCreditNoteRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('view', $invoice);

        if (! $this->invoiceCreditNoteService->canIssueCreditNote($request->user(), $invoice))
        {
            abort(403, __('invoice_credit_note.errors.not_allowed'));
        }

        try
        {
            $result = $this->stripeInvoiceCreditNoteService->issueForInvoice(
                $invoice,
                (string) $request->validated('reason'),
            );
        } catch (\Illuminate\Validation\ValidationException $exception)
        {
            return back()->withInput()->withErrors($exception->errors());
        }

        $reference = $result['number'] ?? $result['credit_note_id'];

        return redirect()
            ->route('invoice.show', $invoice->id)
            ->with('success', __('invoice_credit_note.success', ['reference' => $reference]));
    }
}
